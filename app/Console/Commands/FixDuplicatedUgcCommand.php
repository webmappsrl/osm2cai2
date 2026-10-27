<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GroupsByTimestamp;
use App\Services\UgcDuplicatesService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Wm\WmPackage\Models\UgcPoi;
use Wm\WmPackage\Models\UgcTrack;

/**
 * STRATO 1 di oc:8867 — porting di Wm\WmPackage\Commands\WmFixDuplicatedUgcCommand (oc:8718, origin/main).
 *
 * Divergenze volute rispetto all'originale (annotate in docs/features/8867-.../notes.md):
 * --max-distance ha default 0, report CSV sotto storage/backups, sezione dei gruppi multi-utente.
 */
class FixDuplicatedUgcCommand extends Command
{
    use GroupsByTimestamp;

    /** Valori di --type e modello corrispondente. */
    private const TYPES = ['tracks' => UgcTrack::class, 'pois' => UgcPoi::class];

    protected $signature = 'osm2cai:fix-duplicated-ugc
                            {--type= : tracks oppure pois; senza, entrambi}
                            {--execute : Applica le modifiche; senza, produce solo il report}
                            {--app-id= : Limita ai record con questo app_id}
                            {--max-distance=0 : Oltre questa distanza in metri fra le geometrie il gruppo non viene toccato (0 = solo geometrie identiche)}
                            {--by-timestamp : Raggruppa anche i record senza uuid per utente, istante di compilazione e posizione (inferenza, spenta per default)}';

    protected $description = 'Unisce gli UGC (tracce e POI) duplicati per properties.uuid nel più vecchio, archiviando le copie (oc:8867).';

    public function handle(UgcDuplicatesService $service): int
    {
        $type = $this->option('type');
        if ($type !== null && ! isset(self::TYPES[$type])) {
            $this->error('--type deve essere tracks oppure pois.');

            return self::FAILURE;
        }
        $types = $type !== null ? [$type => self::TYPES[$type]] : self::TYPES;
        $execute = (bool) $this->option('execute');
        $appOption = $this->option('app-id');
        if ($appOption !== null && $appOption !== '' && ! ctype_digit((string) $appOption)) {
            // Il servizio filtra per id numerico: un valore come "iNaturalist" diventerebbe 0 e darebbe zero gruppi in silenzio.
            $this->error('--app-id accetta solo app numeriche (id intero): "'.$appOption.'" non è valido.');

            return self::FAILURE;
        }
        $appId = $appOption !== null && $appOption !== '' ? (int) $appOption : null;
        $maxDistance = (float) $this->option('max-distance');

        // Si verifica prima delle fusioni: un fallimento a fine run arriverebbe a modifiche già committate.
        $backupDir = storage_path('backups');
        if (! is_dir($backupDir) && ! @mkdir($backupDir, 0775, true) && ! is_dir($backupDir)) {
            $this->error('Impossibile creare la cartella del report: '.$backupDir);

            return self::FAILURE;
        }
        if (! is_writable($backupDir)) {
            $this->error('La cartella del report non è scrivibile: '.$backupDir);

            return self::FAILURE;
        }

        $rows = [];
        $csvRows = [];
        $multiUser = [];
        $esiti = [];
        $mediaCancellati = 0;
        $mediaFalliti = 0;
        foreach ($types as $typeName => $modelClass) {
            foreach ($service->groups($modelClass, $appId)->concat($this->groupsByTimestamp($modelClass, $appId)) as $group) {
                // Gli utenti si leggono prima della fusione: dopo, le copie non esistono più.
                $users = $this->usersOf($modelClass, $group);
                $utentiReali = array_filter($users, fn ($u) => $u !== null);
                // Motivi per cui il gruppo non si tocca: vanno distinti, chi legge deve capire perché è lì.
                $motivi = [];
                if ($group['max_distance_m'] > $maxDistance) {
                    $motivi[] = 'geometria diversa';
                }
                // Padre senza utente e una copia con utente: fondere butterebbe via l'attribuzione.
                if ($users[$group['parent_id']] === null && $utentiReali !== []) {
                    $motivi[] = 'padre senza utente';
                }
                $skip = $motivi !== [];
                if ($execute && ! $skip) {
                    $discarded = $service->mergeGroup($modelClass, $group);
                    foreach ($discarded as $media) {
                        // Dopo il commit: cancella riga e file del media doppione. Un fallimento non ferma il run.
                        try {
                            $media->delete();
                            $mediaCancellati++;
                        } catch (\Throwable $e) {
                            $mediaFalliti++;
                            Log::channel('duplicated-ugc')->error('Cancellazione del media doppione fallita', [
                                'media_id' => $media->id,
                                'uuid' => $group['uuid'],
                                'errore' => $e->getMessage(),
                            ]);
                        }
                    }
                }
                $esito = $skip ? 'da verificare ('.implode(', ', $motivi).')' : ($execute ? 'unito' : 'da unire');
                $esiti[$esito] = ($esiti[$esito] ?? 0) + 1;
                $row = [$typeName, $group['uuid'], $group['parent_id'], implode(' ', $group['copy_ids']), sprintf('%.2f', $group['max_distance_m']), $esito];
                $rows[] = $row;
                $csvRows[] = array_merge($row, [implode(' ', array_map(fn ($id) => $id.':'.($users[$id] ?? '-'), array_keys($users)))]);
                // Conta solo gli utenti reali: un user_id NULL non è un utente in più.
                if (count(array_unique($utentiReali)) > 1) {
                    $multiUser[] = [$typeName, $group['uuid'], $group['parent_id'], $users[$group['parent_id']] ?? '-', implode(' ', array_map(fn ($id) => $id.':'.($users[$id] ?? '-'), $group['copy_ids'])), $esito];
                }
                Log::channel('duplicated-ugc')->info('Gruppo UGC duplicato', [
                    'tipo' => $typeName,
                    'uuid' => $group['uuid'],
                    'padre' => $group['parent_id'],
                    'copie' => $group['copy_ids'],
                    'distanza_max_m' => round($group['max_distance_m'], 2),
                    'esito' => $esito,
                ]);
            }
        }

        Log::channel('duplicated-ugc')->info('osm2cai:fix-duplicated-ugc completato', [
            'modalita' => $execute ? 'execute' : 'report',
            'tipi' => array_keys($types),
            'app_id' => $appId,
            'max_distance_m' => $maxDistance,
            'gruppi' => count($rows),
            'gruppi_multi_utente' => count($multiUser),
            'esiti' => $esiti,
            'media_cancellati' => $mediaCancellati,
            'media_falliti' => $mediaFalliti,
        ]);
        $this->table(['tipo', 'uuid', 'padre', 'copie', 'distanza max (m)', 'esito'], $rows);
        if ($multiUser !== []) {
            $this->warn(count($multiUser).' gruppi con record di più utenti. Se il gruppo si fonde resta il record più vecchio (il padre), e il rilevamento è assegnato al suo utente; i gruppi "da verificare" non vengono toccati.');
            $this->table(['tipo', 'uuid', 'padre', 'utente del padre', 'copie (id:utente)', 'esito'], $multiUser);
        }
        $this->info(count($rows).' gruppi trovati'.($execute ? '.' : ' (report, nessuna scrittura).'));
        if ($rows !== []) {
            ksort($esiti);
            $this->info('Riepilogo: '.implode('; ', array_map(fn ($esito, $n) => "{$n} {$esito}", array_keys($esiti), $esiti)).'.');
        }
        if ($execute) {
            $this->info("Media doppioni cancellati: {$mediaCancellati}, falliti: {$mediaFalliti}".($mediaFalliti > 0 ? ' (id nel log duplicated-ugc).' : '.'));
        }

        $status = self::SUCCESS;
        if ($csvRows === []) {
            // Senza gruppi non si lascia un file vuoto.
            $this->info('Nessun gruppo da riportare: report CSV non scritto.');
        } elseif (($csvPath = $this->writeCsv($backupDir, $execute, $csvRows)) !== null) {
            $this->info('Report CSV: '.$csvPath);
        } else {
            $this->error('Report CSV non scritto: impossibile aprire il file in '.$backupDir.($execute ? ' (le fusioni sono già state applicate).' : '.'));
            $status = self::FAILURE;
        }
        $this->warn($execute
            ? 'Fatto. Le copie sono in ugc_duplicates_archive; il rollback si fa dal backup del DB.'
            : 'Report: prima di --execute fai il backup del DB.');

        return $status;
    }

    /**
     * Utente di ogni record del gruppo, padre per primo; null se il record non ha utente.
     *
     * @return array<int, int|null> id del record => user_id
     */
    private function usersOf(string $modelClass, array $group): array
    {
        $ids = array_merge([$group['parent_id']], $group['copy_ids']);
        $byId = DB::table((new $modelClass)->getTable())->whereIn('id', $ids)->pluck('user_id', 'id')->all();

        $users = [];
        foreach ($ids as $id) {
            $users[$id] = $byId[$id] ?? null;
        }

        return $users;
    }

    /**
     * Scrive il report e ne restituisce il percorso, o null se il file non si apre. Senza --execute il
     * nome è fisso e il file si sovrascrive (report rigenerabile); con --execute porta il timestamp,
     * perché l'esecuzione è irripetibile e la sua traccia si conserva.
     */
    private function writeCsv(string $dir, bool $execute, array $csvRows): ?string
    {
        $path = $dir.'/fix-duplicated-ugc'.($execute ? '-'.date('Ymd-His') : '').'.csv';
        $handle = @fopen($path, 'w');
        if ($handle === false) {
            return null;
        }
        fputcsv($handle, ['tipo', 'uuid', 'padre', 'copie', 'distanza max (m)', 'esito', 'utenti'], ',', '"', '');
        foreach ($csvRows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }
        fclose($handle);

        return $path;
    }
}
