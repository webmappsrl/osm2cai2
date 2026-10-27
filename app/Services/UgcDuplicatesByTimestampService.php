<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Wm\WmPackage\Models\Abstracts\GeometryModel;
use Wm\WmPackage\Services\BaseService;

/**
 * STRATO 2 di oc:8867 — codice specifico di osm2cai2, NON presente in wm-package e che non ci andrà
 * (negli altri progetti tutti i rilevamenti hanno l'uuid). Sopravvive all'aggiornamento del package.
 *
 * Riconosce i duplicati dei rilevamenti privi di properties.uuid, che non hanno una chiave dichiarata
 * da raggruppare: la chiave si inferisce da utente, istante di compilazione sul telefono
 * (properties.createdAt) e posizione.
 *
 * Il rischio da evitare è la fusione di monitoraggi legittimi: una sorgente d'acqua viene rilevata
 * più volte nel tempo, dallo stesso utente e nello stesso punto (1.121 coppie in produzione, anche
 * una al mese per un anno). Utente e posizione da soli le unirebbero, cancellando una serie storica.
 * L'istante di compilazione resta identico nei reinvii della stessa scheda e cambia sempre fra due
 * monitoraggi distinti (verificato su 174 coppie di duplicati e 1.121 di monitoraggi, zero falsi
 * positivi). Per questo un record senza createdAt non viene mai unito a nessuno.
 *
 * Fa solo il raggruppamento: la fusione resta a UgcDuplicatesService (strato 1), che qui si richiama
 * soltanto per misurare la distanza. All'aggiornamento di wm-package cambia solo il riferimento a quel
 * servizio: oggi è `App\Services\UgcDuplicatesService` (stesso namespace, quindi senza `use`) e va
 * sostituito con `use Wm\WmPackage\Services\Models\UgcDuplicatesService;`.
 */
class UgcDuplicatesByTimestampService extends BaseService
{
    public function __construct(private readonly UgcDuplicatesService $duplicates) {}

    /**
     * Gruppi della stessa forma di UgcDuplicatesService::groups(), con uuid sempre null.
     *
     * @param  class-string<GeometryModel>  $modelClass
     */
    public function groups(string $modelClass, ?int $appId = null): Collection
    {
        $table = (new $modelClass)->getTable();
        $appFilter = $appId !== null ? 'AND app_id = ?' : '';
        $bindings = $appId !== null ? [$appId] : [];

        // Sono esclusi i record senza uuid E senza istante (createdAt assente o vuoto), quelli senza
        // utente e quelli senza geometria: senza uno dei tre elementi del criterio non si può dire
        // che due record siano la stessa scheda. La geometria deve coincidere esattamente.
        // Sono esclusi anche i record marcati properties.duplicated: sono copie già trattate dalla
        // vecchia bonifica, il loro padre vero è quello indicato da duplication_info e le riunisce
        // la sanatoria; fonderle fra loro lascerebbe il padre senza le sue fotografie. Così i due
        // trattamenti non si sovrappongono per costruzione. L'operatore ? di jsonb si scrive ??.
        $rows = DB::select("
            SELECT array_agg(id ORDER BY created_at, id) AS ids
            FROM {$table}
            WHERE (properties->>'uuid' IS NULL OR properties->>'uuid' = '')
              AND properties->>'createdAt' IS NOT NULL AND properties->>'createdAt' <> ''
              AND NOT (properties ?? 'duplicated')
              AND user_id IS NOT NULL
              AND geometry IS NOT NULL {$appFilter}
            GROUP BY user_id, app_id, properties->>'createdAt', ST_AsEWKB(geometry::geometry)
            HAVING count(*) > 1
            ORDER BY min(id)
        ", $bindings);

        return collect($rows)->map(function ($row) use ($table) {
            $ids = array_map('intval', explode(',', trim($row->ids, '{}')));
            $parentId = array_shift($ids);

            return [
                'uuid' => null,
                'parent_id' => $parentId,
                'copy_ids' => $ids,
                // Con la geometria nella chiave la distanza è 0 per costruzione: si calcola comunque, per
                // restituire la stessa forma dello strato 1 e passare per la stessa guardia del command.
                'max_distance_m' => $this->duplicates->maxDistanceMeters($table, $parentId, $ids),
            ];
        });
    }

    /**
     * Rilevamenti senza uuid che groups() non può mai raggruppare, divisi per il primo motivo che li
     * esclude (in quest'ordine: nessun istante di compilazione, nessun utente, nessuna geometria).
     * Non sono duplicati accertati né esclusi: sono rilevamenti che il criterio non sa valutare, e
     * vanno revisionati a mano (comprese le righe con properties NULL, che il coalesce non lascia cadere). Le copie marcate properties.duplicated non si contano: le tratta la
     * sanatoria.
     *
     * @param  class-string<GeometryModel>  $modelClass
     * @return array{senza_istante: int, senza_utente: int, senza_geometria: int}
     */
    public function excluded(string $modelClass, ?int $appId = null): array
    {
        $table = (new $modelClass)->getTable();
        $appFilter = $appId !== null ? 'AND app_id = ?' : '';
        $bindings = $appId !== null ? [$appId] : [];

        $row = DB::selectOne("
            SELECT count(*) FILTER (WHERE properties->>'createdAt' IS NULL OR properties->>'createdAt' = '') AS senza_istante,
                   count(*) FILTER (WHERE properties->>'createdAt' IS NOT NULL AND properties->>'createdAt' <> '' AND user_id IS NULL) AS senza_utente,
                   count(*) FILTER (WHERE properties->>'createdAt' IS NOT NULL AND properties->>'createdAt' <> '' AND user_id IS NOT NULL AND geometry IS NULL) AS senza_geometria
            FROM {$table}
            WHERE (properties->>'uuid' IS NULL OR properties->>'uuid' = '')
              AND NOT coalesce(jsonb_exists(properties, 'duplicated'), false) {$appFilter}
        ", $bindings);

        return [
            'senza_istante' => (int) $row->senza_istante,
            'senza_utente' => (int) $row->senza_utente,
            'senza_geometria' => (int) $row->senza_geometria,
        ];
    }
}
