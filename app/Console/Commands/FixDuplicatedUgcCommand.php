<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GroupsByTimestamp;
use App\Models\UgcPoi;
use App\Models\UgcTrack;
use App\Services\UgcDuplicatesService;
use App\Services\UgcMediaHashService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * STRATO 1 — NON CANCELLARE (oc:8867). File misto: porting di Wm\WmPackage\Commands\WmFixDuplicatedUgcCommand
 * (oc:8718, origin/main) con in più l'aggancio dello strato 2. Resta anche dopo l'aggiornamento di
 * wm-package (è lo schedulato delle 06:00): cambiano solo i `use` di UgcDuplicatesService e UgcMediaHashService.
 *
 * Divergenze volute rispetto all'originale (annotate in docs/features/8867-.../notes.md):
 * --max-distance ha default 0, report CSV sotto storage/backups, sezione dei gruppi multi-utente.
 */
class FixDuplicatedUgcCommand extends Command
{
    use GroupsByTimestamp;

    /** Al posto dell'uuid nei report dei gruppi riconosciuti per istante di compilazione (strato 2). */
    private const CRITERIO_ISTANTE = '(per istante)';

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

        // Guardia sull'ordine di esecuzione: vale anche in dry-run (vedi guardiaCopieMarcate()).
        if (! $this->guardiaCopieMarcate($types)) {
            return self::FAILURE;
        }
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
        // Solo in dry-run: stima delle fotografie dei gruppi che si fonderebbero, letta senza scrivere.
        $stima = ['spostate' => 0, 'scartate' => 0, 'senza_hash' => 0];
        // Solo con --by-timestamp: rilevamenti senza uuid che il raggruppamento non può trattare.
        $esclusi = [];
        foreach ($types as $typeName => $modelClass) {
            foreach ($service->groups($modelClass, $appId)->concat($this->groupsByTimestamp($modelClass, $appId)) as $group) {
                // Gli utenti si leggono prima della fusione: dopo, le copie non esistono più.
                $users = $this->usersOf($modelClass, $group);
                // I gruppi per istante vengono da un'inferenza, non da una chiave dichiarata: si vede nel report.
                $uuidLabel = $group['uuid'] ?? self::CRITERIO_ISTANTE;
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
                if (! $execute && ! $skip) {
                    foreach ($this->stimaMedia($modelClass, $group) as $chiave => $n) {
                        $stima[$chiave] += $n;
                    }
                }
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
                                'uuid' => $uuidLabel,
                                'errore' => $e->getMessage(),
                            ]);
                        }
                    }
                }
                $esito = $skip ? 'da verificare ('.implode(', ', $motivi).')' : ($execute ? 'unito' : 'da unire');
                $esiti[$esito] = ($esiti[$esito] ?? 0) + 1;
                $row = [$typeName, $uuidLabel, $group['parent_id'], implode(' ', $group['copy_ids']), sprintf('%.2f', $group['max_distance_m']), $esito];
                $rows[] = $row;
                $csvRows[] = array_merge($row, [implode(' ', array_map(fn ($id) => $id.':'.($users[$id] ?? '-'), array_keys($users)))]);
                // Conta solo gli utenti reali: un user_id NULL non è un utente in più.
                if (count(array_unique($utentiReali)) > 1) {
                    $multiUser[] = [$typeName, $uuidLabel, $group['parent_id'], $users[$group['parent_id']] ?? '-', implode(' ', array_map(fn ($id) => $id.':'.($users[$id] ?? '-'), $group['copy_ids'])), $esito];
                }
                Log::channel('duplicated-ugc')->info('Gruppo UGC duplicato', [
                    'tipo' => $typeName,
                    'uuid' => $uuidLabel,
                    'padre' => $group['parent_id'],
                    'copie' => $group['copy_ids'],
                    'distanza_max_m' => round($group['max_distance_m'], 2),
                    'esito' => $esito,
                ]);
            }
            if (($fuoriGruppo = $this->excludedByTimestamp($modelClass, $appId)) !== null) {
                $esclusi[$typeName] = $fuoriGruppo;
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
            'media_stimati' => $execute ? null : $stima,
            'senza_uuid_esclusi' => $esclusi ?: null,
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
        } else {
            $this->riferisciStimaMedia($stima);
        }
        if ($esclusi !== []) {
            $this->riferisciEsclusi($esclusi);
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
     * Guardia sull'ordine di esecuzione. Se in ugc_pois restano righe con la chiave
     * properties->duplicated (la vecchia bonifica), la sanatoria deve girare prima: fondendo una copia
     * marcata le sue chiavi (duplicated, duplication_info, has_been_duplicated) passano sul record
     * tenuta, che poi la sanatoria non ripulisce più (esclude per costruzione ogni riga con la chiave
     * duplicated). Il risultato sono record legittimi marcati in modo permanente.
     *
     * Vale anche in dry-run, e non come semplice avviso: i gruppi che il dry-run annuncerebbe prima
     * della sanatoria non sono quelli che la bonifica unirebbe dopo (le copie marcate cambiano il
     * conteggio), quindi i numeri letti sarebbero fuorvianti; l'esecuzione schedulata delle 06:00, poi,
     * non deve passare inosservata. Si controlla la chiave, di qualunque valore, come fa la sanatoria
     * per decidere se una riga è ripulibile.
     *
     * @return bool false se il command deve fermarsi
     */
    private function guardiaCopieMarcate(array $types): bool
    {
        // Si controllano solo le tabelle dei tipi da trattare (--type): UgcDuplicatesService::groups() non esclude le righe marcate.
        $marcate = 0;
        $tabelle = [];
        foreach ($types as $modelClass) {
            $tabella = (new $modelClass)->getTable();
            $n = DB::table($tabella)->whereRaw("jsonb_exists(properties, 'duplicated')")->count();
            if ($n > 0) {
                $marcate += $n;
                $tabelle[] = $tabella;
            }
        }
        if ($marcate === 0) {
            return true;
        }

        $this->error('In '.implode(', ', $tabelle)." ci sono ancora {$marcate} righe con la chiave properties.duplicated (copie lasciate dalla vecchia bonifica).");
        $this->error('Esegui prima la sanatoria: php artisan osm2cai:recover-previous-duplicate-fix (dry-run) e poi con --execute.');
        $this->line('Fondere ora le copie marcate farebbe passare duplicated, duplication_info e has_been_duplicated sui record tenuti, e la sanatoria non le ripulirebbe più.');
        $this->line('Se dopo la sanatoria restano righe con la chiave duplicated (valore diverso dal boolean true) vanno riviste a mano: la sanatoria non le tocca.');
        Log::channel('duplicated-ugc')->warning('osm2cai:fix-duplicated-ugc fermato: copie marcate duplicated ancora presenti', ['righe' => $marcate]);

        return false;
    }

    /**
     * Stima in sola lettura delle fotografie di un gruppo che si fonderebbe, con la stessa regola di
     * UgcDuplicatesService::moveMedia(): una fotografia della copia con lo stesso sha256 di una già
     * presente sul padre (o su una copia precedente) si scarta, le altre si spostano. Legge soltanto
     * custom_properties.sha256 e non chiama mai UgcMediaHashService::hashOf(), che per i media senza
     * hash scaricherebbe il file e lo salverebbe: un media senza hash conta come «da spostare» e
     * viene segnalato a parte.
     *
     * @return array{spostate: int, scartate: int, senza_hash: int}
     */
    private function stimaMedia(string $modelClass, array $group): array
    {
        $morph = (new $modelClass)->getMorphClass();
        $ids = array_merge([$group['parent_id']], $group['copy_ids']);
        $hash = DB::raw("custom_properties->>'".UgcMediaHashService::HASH_PROPERTY."' AS hash");
        $perRecord = DB::table('media')->where('model_type', $morph)->whereIn('model_id', $ids)
            ->orderBy('id')->get(['id', 'model_id', $hash])->groupBy('model_id');

        $stima = ['spostate' => 0, 'scartate' => 0, 'senza_hash' => 0];
        $noti = [];
        foreach ($perRecord->get($group['parent_id'], collect()) as $media) {
            if (is_string($media->hash) && $media->hash !== '') {
                $noti[$media->hash] = true;
            } else {
                // Il padre non ha l'hash: non si può sapere se la copia lo duplica, quindi si segnala.
                $stima['senza_hash']++;
            }
        }
        foreach ($group['copy_ids'] as $copyId) {
            foreach ($perRecord->get($copyId, collect()) as $media) {
                if (! is_string($media->hash) || $media->hash === '') {
                    $stima['senza_hash']++;
                    $stima['spostate']++;

                    continue;
                }
                if (isset($noti[$media->hash])) {
                    $stima['scartate']++;

                    continue;
                }
                $noti[$media->hash] = true;
                $stima['spostate']++;
            }
        }

        return $stima;
    }

    /** @param array{spostate: int, scartate: int, senza_hash: int} $stima */
    private function riferisciStimaMedia(array $stima): void
    {
        $this->info("Fotografie dei gruppi da unire (stima, nessuna scrittura): {$stima['spostate']} da spostare sul padre, {$stima['scartate']} da scartare come doppioni (stesso sha256).");
        if ($stima['senza_hash'] > 0) {
            $this->warn("{$stima['senza_hash']} fotografie senza sha256: l'hash non è disponibile e non si calcola in dry-run, quelle delle copie contano come da spostare (la stima degli scarti è per difetto). Esegui prima osm2cai:backfill-ugc-media-hash --execute.");
        }
    }

    /** @param array<string, array{senza_istante: int, senza_utente: int, senza_geometria: int}> $esclusi per tipo */
    private function riferisciEsclusi(array $esclusi): void
    {
        $totale = array_sum(array_map('array_sum', $esclusi));
        if ($totale === 0) {
            return;
        }
        $this->info("Rilevamenti senza uuid che --by-timestamp non può valutare: {$totale}. Non finiscono in nessun gruppo (non sono necessariamente duplicati): se servono controlli, vanno revisionati a mano.");
        $this->table(
            ['tipo', 'senza istante di compilazione', 'senza utente', 'senza geometria'],
            array_map(fn ($tipo, $n) => [$tipo, $n['senza_istante'], $n['senza_utente'], $n['senza_geometria']], array_keys($esclusi), $esclusi)
        );
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
