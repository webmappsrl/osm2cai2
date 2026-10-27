<?php

namespace App\Console\Commands;

use App\Models\UgcPoi;
use App\Models\User;
use App\Services\UgcDuplicatesService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * STRATO 2 di oc:8867 — resta anche dopo l'aggiornamento di wm-package.
 *
 * Sana lo stato lasciato dalla precedente esecuzione del command di bonifica, che esiste solo in questo
 * progetto: invece di cancellare i POI duplicati li aveva marcati con properties.duplicated = true e
 * riassegnati all'utente di servizio duplicated-ugc-pois@webmapp.it, lasciando le fotografie attaccate
 * alle copie e le chiavi duplication_info / has_been_duplicated anche su schede che non sono copie.
 *
 * Una copia il cui padre non esiste più è l'unico superstite del suo gruppo: se l'utente originale è
 * valido torna rilevamento normale del suo autore (user_id ripristinato, chiavi di duplicazione tolte,
 * nessuna cancellazione); altrimenti resta com'è e va a revisione manuale.
 *
 * Non ripete la logica dello strato 1: lo spostamento dei media (con lo scarto per hash) è
 * UgcDuplicatesService::moveMedia(). Qui c'è solo ciò che è specifico di questo stato.
 * Le tracce non furono mai trattate dalla vecchia bonifica: il command non le tocca, ma ne segnala
 * eventuali tracce con le stesse chiavi o assegnate all'utente di servizio.
 */
class RecoverPreviousDuplicateFixCommand extends Command
{
    private const SANABILE = 'sanabile';

    private const ORFANA = 'orfana';

    private const DA_REVISIONARE = 'da_revisionare';

    private const SERVICE_USER_EMAIL = 'duplicated-ugc-pois@webmapp.it';

    /** Chiavi lasciate dalla vecchia bonifica anche sulle schede che non sono copie. */
    private const INHERITED_KEYS = ['duplication_info', 'has_been_duplicated'];

    /** Copia marcata. jsonb_exists al posto dell'operatore ?, che PDO leggerebbe come placeholder. */
    private const IS_MARKED_COPY = "coalesce(properties->'duplicated' = 'true'::jsonb, false)";

    protected $signature = 'osm2cai:recover-previous-duplicate-fix
                            {--execute : Applica le modifiche; senza, produce solo la segnalazione}';

    protected $description = 'Sana lo stato lasciato dalla bonifica precedente dei POI duplicati: sposta le foto sulle schede tenute, archivia e cancella le copie marcate, ripulisce le chiavi ereditate ed elimina l\'utente di servizio (oc:8867).';

    public function handle(UgcDuplicatesService $service): int
    {
        $execute = (bool) $this->option('execute');
        $morph = (new UgcPoi)->getMorphClass();
        $serviceUserId = DB::table('users')->where('email', self::SERVICE_USER_EMAIL)->value('id');

        $copyIds = DB::table('ugc_pois')->whereRaw(self::IS_MARKED_COPY)->orderBy('id')->pluck('id')->all();
        $this->info(count($copyIds).' copie marcate duplicated trovate'.($execute ? '.' : ' (segnalazione, nessuna scrittura).'));

        $daRevisionare = [];
        $trattate = 0;
        $orfane = 0;
        $falliti = 0;
        $mediaSuCopie = 0;
        $mediaSpostati = 0;
        $mediaCancellati = 0;
        $mediaFalliti = 0;
        $copieDellUtenteDiServizio = 0;

        foreach ($copyIds as $copyId) {
            $copy = DB::table('ugc_pois')->where('id', $copyId)->selectRaw("id, user_id, properties->'duplication_info' AS info")->first();
            $info = json_decode((string) $copy->info, true);
            $info = is_array($info) ? $info : [];
            $parentId = $info['parent_ugc_poi_id'] ?? null;
            $parentUserId = $info['parent_ugc_poi_user_id'] ?? null;

            [$esito, $motivo] = $this->classifica($parentId, $parentUserId);
            if ($esito === self::DA_REVISIONARE) {
                // Una copia che non si sa dove ricondurre non si tocca: resta com'è, a revisione manuale.
                $daRevisionare[] = [$copyId, $parentId ?? '-', $motivo];
                Log::channel('duplicated-ugc')->warning('Copia marcata da revisionare a mano', ['copia' => $copyId, 'padre' => $parentId, 'motivo' => $motivo]);

                continue;
            }
            if ($esito === self::ORFANA) {
                // Padre sparito e utente originale valido: è l'unico superstite del gruppo, non è copia di niente.
                // Diventa un rilevamento normale del suo autore; non si cancella. Resta nell'elenco perché il caso è anomalo.
                $daRevisionare[] = [$copyId, $parentId, $execute
                    ? "{$motivo}: sanata, ora rilevamento normale dell'utente {$parentUserId}"
                    : "{$motivo}: verrebbe sanata, rilevamento normale dell'utente {$parentUserId}"];
                if ($copy->user_id !== null && (int) $copy->user_id === (int) $serviceUserId) {
                    $copieDellUtenteDiServizio++;
                }
                if ($execute) {
                    $this->sanaOrfana((int) $copyId, (int) $parentUserId, $info);
                }
                $orfane++;

                continue;
            }

            $mediaDellaCopia = DB::table('media')->where('model_type', $morph)->where('model_id', $copyId)->count();
            $mediaSuCopie += $mediaDellaCopia;
            if ($copy->user_id !== null && (int) $copy->user_id === (int) $serviceUserId) {
                $copieDellUtenteDiServizio++;
            }

            if (! $execute) {
                $trattate++;

                continue;
            }

            try {
                [$spostati, $scartati] = $this->sanaCopia($service, $morph, (int) $copyId, (int) $parentId, (int) $parentUserId);
            } catch (\Throwable $e) {
                // La transazione della copia è già annullata: si prosegue con le altre.
                $falliti++;
                Log::channel('duplicated-ugc')->error('Sanatoria della copia fallita', ['copia' => $copyId, 'padre' => $parentId, 'errore' => $e->getMessage()]);
                $this->error("Copia {$copyId}: {$e->getMessage()}");

                continue;
            }

            $trattate++;
            $mediaSpostati += $spostati;
            // Dopo il commit: cancella riga e file del media doppione. Un fallimento non ferma il run.
            foreach ($scartati as $media) {
                try {
                    $media->delete();
                    $mediaCancellati++;
                } catch (\Throwable $e) {
                    $mediaFalliti++;
                    Log::channel('duplicated-ugc')->error('Cancellazione del media doppione fallita', ['media_id' => $media->id, 'copia' => $copyId, 'errore' => $e->getMessage()]);
                }
            }
        }

        $this->riferisciCopie($execute, $trattate, $orfane, $falliti, $mediaSuCopie, $mediaSpostati, $mediaCancellati, $mediaFalliti, $daRevisionare);

        $schedeDaRipulire = $this->ripulisciChiaviEreditate($execute);
        $this->info($execute
            ? "Schede ripulite dalle chiavi ereditate: {$schedeDaRipulire}."
            : "Schede da ripulire dalle chiavi ereditate: {$schedeDaRipulire}.");

        $this->segnalaTracce($serviceUserId);
        $this->gestisciUtenteDiServizio($execute, $serviceUserId, $copieDellUtenteDiServizio);

        Log::channel('duplicated-ugc')->info('osm2cai:recover-previous-duplicate-fix completato', [
            'modalita' => $execute ? 'execute' : 'report',
            'copie_marcate' => count($copyIds),
            'copie_trattate' => $trattate,
            'copie_orfane_sanate' => $orfane,
            'copie_da_revisionare' => count($daRevisionare),
            'copie_fallite' => $falliti,
            'media_sulle_copie' => $mediaSuCopie,
            'media_spostati' => $mediaSpostati,
            'media_cancellati' => $mediaCancellati,
            'media_falliti' => $mediaFalliti,
            'schede_ripulite' => $schedeDaRipulire,
        ]);

        if ($execute) {
            $this->warn('Fatto. Le copie sono in ugc_duplicates_archive; il rollback si fa dal backup del DB.');
        } else {
            $this->warn('Segnalazione: prima di --execute fai il backup del DB.');
        }

        return $falliti > 0 || $mediaFalliti > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Che cosa fare della copia: SANABILE (padre e utente originale validi), ORFANA (padre inesistente
     * ma utente originale valido) o DA_REVISIONARE (resta com'è). Il secondo elemento è il motivo.
     *
     * @return array{0: string, 1: ?string}
     */
    private function classifica(mixed $parentId, mixed $parentUserId): array
    {
        if (! is_numeric($parentId)) {
            return [self::DA_REVISIONARE, 'padre non indicato in duplication_info'];
        }
        $padre = DB::table('ugc_pois')
            ->where('id', (int) $parentId)
            ->selectRaw(self::IS_MARKED_COPY.' AS copia')
            ->first();
        if ($padre !== null && $padre->copia) {
            // Spostare le foto su un record che a sua volta è una copia marcata le perderebbe di vista.
            return [self::DA_REVISIONARE, 'il padre è a sua volta una copia marcata'];
        }
        // Se il padre manca lo si dice anche quando il vero ostacolo è l'utente.
        $prefisso = $padre === null ? 'il padre non esiste più e ' : '';
        if (! is_numeric($parentUserId)) {
            return [self::DA_REVISIONARE, $prefisso.'utente originale non indicato in duplication_info'];
        }
        if (! DB::table('users')->where('id', (int) $parentUserId)->exists()) {
            return [self::DA_REVISIONARE, $prefisso.'l\'utente originale non esiste più'];
        }

        return $padre === null ? [self::ORFANA, 'il padre non esiste più'] : [self::SANABILE, null];
    }

    /** Copia senza più padre: restituisce il rilevamento al suo autore e toglie ogni traccia di duplicazione. */
    private function sanaOrfana(int $copyId, int $userId, array $info): void
    {
        DB::update(
            "UPDATE ugc_pois SET user_id = ?, properties = properties - 'duplicated' - 'duplication_info' - 'has_been_duplicated' WHERE id = ?",
            [$userId, $copyId]
        );
        Log::channel('duplicated-ugc')->warning('Copia orfana ripristinata come rilevamento normale', ['copia' => $copyId, 'utente' => $userId, 'duplication_info' => $info]);
    }

    /**
     * Sana una copia in una transazione: foto sul padre, utente originale, archivio, cancellazione.
     *
     * @return array{0: int, 1: \Wm\WmPackage\Models\Media[]} foto spostate, media scartati come doppioni (da cancellare dopo il commit)
     */
    private function sanaCopia(UgcDuplicatesService $service, string $morph, int $copyId, int $parentId, int $parentUserId): array
    {
        return DB::transaction(function () use ($service, $morph, $copyId, $parentId, $parentUserId) {
            [$spostatiPerCopia, $scartati] = $service->moveMedia($parentId, [$copyId], $morph);

            // L'utente si ripristina prima di archiviare: l'archivio deve registrare chi ha fatto il rilevamento.
            DB::update('UPDATE ugc_pois SET user_id = ? WHERE id = ?', [$parentUserId, $copyId]);

            DB::insert("
                INSERT INTO ugc_duplicates_archive (model_type, original_id, reference_id, uuid, user_id, app_id, properties, geometry, media_ids, original_created_at, original_updated_at, archived_at, created_at, updated_at)
                SELECT ?, id, ?, properties->>'uuid', user_id, app_id, properties::jsonb, geometry, ?::jsonb, created_at, updated_at, now(), now(), now()
                FROM ugc_pois WHERE id = ?
            ", [$morph, $parentId, json_encode($spostatiPerCopia[$copyId] ?? []), $copyId]);

            DB::delete('DELETE FROM ugc_pois WHERE id = ?', [$copyId]);

            return [count($spostatiPerCopia[$copyId] ?? []), $scartati];
        });
    }

    private function riferisciCopie(bool $execute, int $trattate, int $orfane, int $falliti, int $mediaSuCopie, int $mediaSpostati, int $mediaCancellati, int $mediaFalliti, array $daRevisionare): void
    {
        if ($execute) {
            $this->info("Copie sanate (archiviate e cancellate): {$trattate}, fallite: {$falliti}. Copie orfane ripristinate come rilevamenti normali: {$orfane}.");
            $this->info("Foto spostate sulla scheda tenuta: {$mediaSpostati}; doppioni cancellati: {$mediaCancellati}, falliti: {$mediaFalliti}".($mediaFalliti > 0 ? ' (id nel log duplicated-ugc).' : '.'));
        } else {
            $this->info("Copie da sanare (archiviare e cancellare): {$trattate}. Copie orfane da ripristinare come rilevamenti normali: {$orfane}.");
            // Senza --execute non si calcolano gli hash: scriverebbero nei custom_properties dei media.
            $this->info("Media attaccati a quelle copie: {$mediaSuCopie} (quanti si spostano e quanti sono doppioni lo dice l'hash, calcolato solo con --execute).");
        }

        if ($daRevisionare !== []) {
            $this->warn(count($daRevisionare).' copie da guardare a mano (le orfane con utente valido si sanano comunque, le altre restano intatte).');
            $this->table(['copia', 'padre', 'motivo'], $daRevisionare);
        }
    }

    /** Toglie le chiavi ereditate dalle schede che non sono copie. Restituisce quante sono (o sarebbero). */
    private function ripulisciChiaviEreditate(bool $execute): int
    {
        $query = DB::table('ugc_pois')
            ->whereRaw('NOT '.self::IS_MARKED_COPY)
            ->whereRaw("(jsonb_exists(properties, 'duplication_info') OR jsonb_exists(properties, 'has_been_duplicated'))");

        if (! $execute) {
            return $query->count();
        }

        // Aggiornamento a livello di riga: nessun evento, nessun updated_at toccato, la scheda resta com'è.
        return $query->update(['properties' => DB::raw("properties - 'duplication_info' - 'has_been_duplicated'")]);
    }

    /** Le tracce non furono trattate dalla vecchia bonifica: se risultano segnate lo si dice, non si toccano. */
    private function segnalaTracce(mixed $serviceUserId): void
    {
        $segnate = DB::table('ugc_tracks')
            ->whereRaw("(jsonb_exists(properties, 'duplicated') OR jsonb_exists(properties, 'duplication_info') OR jsonb_exists(properties, 'has_been_duplicated'))")
            ->count();
        $assegnate = $serviceUserId !== null ? DB::table('ugc_tracks')->where('user_id', $serviceUserId)->count() : 0;

        if ($segnate > 0 || $assegnate > 0) {
            $this->warn("Tracce con chiavi della vecchia bonifica: {$segnate}; assegnate all'utente di servizio: {$assegnate}. Questo command non le tratta.");
        }
    }

    private function gestisciUtenteDiServizio(bool $execute, mixed $serviceUserId, int $copieDaSanare): void
    {
        if ($serviceUserId === null) {
            $this->info('Utente di servizio '.self::SERVICE_USER_EMAIL.' assente: niente da eliminare.');

            return;
        }

        $residui = $this->recordAssegnati((int) $serviceUserId);
        if (! $execute) {
            // Simula l'esito: le copie sanabili spariranno, le altre restano assegnate all'utente.
            if (isset($residui['ugc_pois.user_id'])) {
                $residui['ugc_pois.user_id'] -= $copieDaSanare;
                if ($residui['ugc_pois.user_id'] <= 0) {
                    unset($residui['ugc_pois.user_id']);
                }
            }
        }

        if ($residui !== []) {
            $elenco = implode(', ', array_map(fn ($col, $n) => "{$col}: {$n}", array_keys($residui), $residui));
            $this->warn('Utente di servizio '.self::SERVICE_USER_EMAIL.' '.($execute ? 'NON eliminato' : 'non verrebbe eliminato').": ha ancora record assegnati ({$elenco}).");
            Log::channel('duplicated-ugc')->warning('Utente di servizio non eliminato', ['user_id' => $serviceUserId, 'residui' => $residui, 'modalita' => $execute ? 'execute' : 'report']);

            return;
        }

        if (! $execute) {
            $this->info('Utente di servizio '.self::SERVICE_USER_EMAIL." (id {$serviceUserId}) verrebbe eliminato: nessun record resterebbe assegnato.");

            return;
        }

        DB::transaction(function () use ($serviceUserId) {
            // Ruoli e permessi sono morph senza foreign key: vanno tolti insieme all'utente.
            $morph = (new User)->getMorphClass();
            foreach (['model_has_roles', 'model_has_permissions'] as $pivot) {
                DB::table($pivot)->where('model_type', $morph)->where('model_id', $serviceUserId)->delete();
            }
            DB::table('users')->where('id', $serviceUserId)->delete();
        });
        $this->info('Utente di servizio '.self::SERVICE_USER_EMAIL." (id {$serviceUserId}) eliminato.");
        Log::channel('duplicated-ugc')->info('Utente di servizio eliminato', ['user_id' => $serviceUserId]);
    }

    /**
     * Record che puntano all'utente, da qualunque tabella con una foreign key verso users.
     * Si leggono i vincoli dal catalogo: se domani una tabella nuova referenzia users, l'utente
     * non viene eliminato da sotto a dei dati, e un DELETE non fallisce a metà.
     *
     * @return array<string, int> "tabella.colonna" => numero di record (solo se > 0)
     */
    private function recordAssegnati(int $userId): array
    {
        $fk = DB::select("
            SELECT c.conrelid::regclass::text AS tabella, a.attname AS colonna
            FROM pg_constraint c
            JOIN pg_attribute a ON a.attrelid = c.conrelid AND a.attnum = ANY(c.conkey)
            WHERE c.contype = 'f' AND c.confrelid = 'users'::regclass AND array_length(c.conkey, 1) = 1
            ORDER BY 1, 2
        ");

        $residui = [];
        foreach ($fk as $riga) {
            $n = DB::table($riga->tabella)->where($riga->colonna, $userId)->count();
            if ($n > 0) {
                $residui["{$riga->tabella}.{$riga->colonna}"] = $n;
            }
        }

        return $residui;
    }
}
