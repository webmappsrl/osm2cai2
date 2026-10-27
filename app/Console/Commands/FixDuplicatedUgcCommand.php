<?php

namespace App\Console\Commands;

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
    /** Valori di --type e modello corrispondente. */
    private const TYPES = ['tracks' => UgcTrack::class, 'pois' => UgcPoi::class];

    protected $signature = 'osm2cai:fix-duplicated-ugc
                            {--type= : tracks oppure pois; senza, entrambi}
                            {--execute : Applica le modifiche; senza, produce solo il report}
                            {--app-id= : Limita ai record con questo app_id}
                            {--max-distance=0 : Oltre questa distanza in metri fra le geometrie il gruppo non viene toccato (0 = solo geometrie identiche)}';

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
        $appId = $this->option('app-id') !== null && $this->option('app-id') !== '' ? (int) $this->option('app-id') : null;
        $maxDistance = (float) $this->option('max-distance');

        $rows = [];
        $csvRows = [];
        $multiUser = [];
        foreach ($types as $typeName => $modelClass) {
            foreach ($service->groups($modelClass, $appId) as $group) {
                // Gli utenti si leggono prima della fusione: dopo, le copie non esistono più.
                $users = $this->usersOf($modelClass, $group);
                // Motivi per cui il gruppo non si tocca: vanno distinti, chi legge deve capire perché è lì.
                $motivi = [];
                if ($group['max_distance_m'] > $maxDistance) {
                    $motivi[] = 'geometria diversa';
                }
                // Padre senza utente e una copia con utente: fondere butterebbe via l'attribuzione.
                if ($users[$group['parent_id']] === '-' && count(array_filter($users, fn ($u) => $u !== '-')) > 0) {
                    $motivi[] = 'padre senza utente';
                }
                $skip = $motivi !== [];
                if ($execute && ! $skip) {
                    $discarded = $service->mergeGroup($modelClass, $group);
                    foreach ($discarded as $media) {
                        // Dopo il commit: cancella riga e file del media doppione.
                        $media->delete();
                    }
                }
                $esito = $skip ? 'da verificare ('.implode(', ', $motivi).')' : ($execute ? 'unito' : 'da unire');
                $row = [$typeName, $group['uuid'], $group['parent_id'], implode(' ', $group['copy_ids']), sprintf('%.2f', $group['max_distance_m']), $esito];
                $rows[] = $row;
                $csvRows[] = array_merge($row, [implode(' ', array_map(fn ($id) => $id.':'.$users[$id], array_keys($users)))]);
                if (count(array_unique($users)) > 1) {
                    // Si tiene comunque il più vecchio: la sezione serve a vedere prima a chi resta assegnato.
                    $multiUser[] = [$typeName, $group['uuid'], $group['parent_id'], $users[$group['parent_id']], implode(' ', array_map(fn ($id) => $id.':'.$users[$id], $group['copy_ids'])), $esito];
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
        ]);
        $this->table(['tipo', 'uuid', 'padre', 'copie', 'distanza max (m)', 'esito'], $rows);
        if ($multiUser !== []) {
            $this->warn(count($multiUser).' gruppi multi-utente: il rilevamento resta assegnato all\'utente del padre (il record più vecchio).');
            $this->table(['tipo', 'uuid', 'padre', 'utente che resta', 'copie (id:utente)', 'esito'], $multiUser);
        }
        $this->info(count($rows).' gruppi trovati'.($execute ? '.' : ' (report, nessuna scrittura).'));
        // Senza gruppi non si lascia un file vuoto: lo schedule giornaliero ne accumulerebbe uno al giorno.
        $this->info($csvRows !== [] ? 'Report CSV: '.$this->writeCsv($csvRows) : 'Nessun gruppo da riportare: report CSV non scritto.');
        $this->warn($execute
            ? 'Fatto. Le copie sono in ugc_duplicates_archive; il rollback si fa dal backup del DB.'
            : 'Report: prima di --execute fai il backup del DB.');

        return self::SUCCESS;
    }

    /**
     * Utente di ogni record del gruppo, padre per primo.
     *
     * @return array<int, int|string> id del record => user_id
     */
    private function usersOf(string $modelClass, array $group): array
    {
        $ids = array_merge([$group['parent_id']], $group['copy_ids']);
        $byId = DB::table((new $modelClass)->getTable())->whereIn('id', $ids)->pluck('user_id', 'id')->all();

        $users = [];
        foreach ($ids as $id) {
            $users[$id] = $byId[$id] ?? '-';
        }

        return $users;
    }

    /** Scrive il report in storage/backups e ne restituisce il percorso. */
    private function writeCsv(array $csvRows): string
    {
        $dir = storage_path('backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir.'/fix-duplicated-ugc-'.date('Ymd-His').'.csv';
        $handle = fopen($path, 'w');
        fputcsv($handle, ['tipo', 'uuid', 'padre', 'copie', 'distanza max (m)', 'esito', 'utenti'], ',', '"', '');
        foreach ($csvRows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }
        fclose($handle);

        return $path;
    }
}
