<?php

namespace App\Console\Commands;

use App\Models\EcPoi;
use App\Models\HikingRoute;
use App\Models\User;
use App\Services\GeometryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Wm\WmPackage\Models\TaxonomyPoiType;

class PopulateOsmfeaturesIdFromSicaiDbCommand extends Command
{
    protected $signature = 'osm2cai:populate-osmfeatures-id-from-sicai-db
                            {--dry-run : Esegue senza scrivere sul database}
                            {--limit= : Processa al massimo N route (utile per test)}';

    protected $description = 'Popola osmfeatures_id delle hikingroute app_2 leggendo tappa/osmid da DB Sicai (SI_Tappe e SICAI_MTB). Solo letture su Sicai.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        if ($dryRun) {
            $this->warn('Modalità dry-run: nessuna modifica al database.');
        }

        if (! config('database.connections.sicai_postgis.host')) {
            $this->error('Connessione Sicai PostGIS non configurata: imposta SICAI_POSTGIS_DB_* nel .env');

            return self::FAILURE;
        }

        $this->info('Recupero dati dal DB Sicai (solo letture)...');

        try {
            $allFeatures = $this->fetchFeaturesFromSicaiDb();
        } catch (\Throwable $e) {
            $this->error('Errore lettura DB Sicai: ' . $e->getMessage());

            return self::FAILURE;
        }

        $totalFeatures = count($allFeatures);
        $this->info("Trovate {$totalFeatures} feature totali dal DB Sicai.");
        $this->newLine();

        // Carica tutte le hikingroute della app_2 con ref
        $this->info('Caricamento hikingroute della app_2...');
        $hikingRoutesQuery = HikingRoute::query()
            ->where('app_id', 2)
            ->where(function ($q) {
                $q->whereRaw("properties->>'ref' IS NOT NULL")
                    ->orWhereRaw("osmfeatures_data->'properties'->>'ref' IS NOT NULL");
            });

        if ($limit !== null && $limit > 0) {
            $hikingRoutesQuery->limit($limit);
        }

        $hikingRoutes = $hikingRoutesQuery->get();
        $this->info("Trovate {$hikingRoutes->count()} hikingroute della app_2 con ref.");
        $this->newLine();

        // Crea una mappa per lookup veloce: ref => hikingroute
        $hikingRoutesByRef = [];
        foreach ($hikingRoutes as $route) {
            $ref = $this->getRefFromRoute($route);
            if ($ref !== null) {
                if (! isset($hikingRoutesByRef[$ref])) {
                    $hikingRoutesByRef[$ref] = [];
                }
                $hikingRoutesByRef[$ref][] = $route;
            }
        }

        $this->info('Hikingroute mappate per ref: ' . count($hikingRoutesByRef) . ' ref univoci.');
        $this->newLine();

        // Carica le hikingroute della app_1 con osm_id per lookup veloce
        $this->info('Caricamento hikingroute della app_1 per lookup osm_id...');
        $app1RoutesQuery = HikingRoute::query()
            ->where('app_id', 1)
            ->whereNotNull('osmfeatures_id')
            ->whereRaw("osmfeatures_data->'properties'->>'osm_id' IS NOT NULL");

        $app1Routes = $app1RoutesQuery->get();

        $app1RoutesByOsmId = [];
        foreach ($app1Routes as $route) {
            $osmId = $this->getOsmIdFromRoute($route);
            if ($osmId !== null) {
                $app1RoutesByOsmId[$osmId] = $route;
            }
        }

        $this->info('Hikingroute app_1 mappate per osm_id: ' . count($app1RoutesByOsmId) . ' osm_id univoci.');
        $this->newLine();

        $matchedWithOsmId = 0;
        $matchedWithParentId = 0;
        $updated = 0;
        $skipped = 0;

        $this->info('Inizio confronto e aggiornamento...');
        $this->newLine();

        foreach ($allFeatures as $feature) {
            $tappa = $feature['properties']['sicai']['tappa'] ?? $feature['properties']['tappa'] ?? null;

            if ($tappa === null) {
                $skipped++;
                continue;
            }

            if (! isset($hikingRoutesByRef[$tappa])) {
                $skipped++;
                continue;
            }

            $matchingRoutes = $hikingRoutesByRef[$tappa];
            $osmid = $feature['properties']['osmid'] ?? $feature['properties']['openstreetmap'] ?? null;

            $osmfeaturesId = null;
            $app1RouteId = null;
            $app1Route = null;

            if (! empty($osmid)) {
                $osmid = (string) $osmid;
                $osmfeaturesId = 'R' . $osmid;

                if (isset($app1RoutesByOsmId[$osmid])) {
                    $app1Route = $app1RoutesByOsmId[$osmid];
                    $app1RouteId = $app1Route->id;
                }
            } else {
                $skipped++;
                continue;
            }

            $matchedWithOsmId += count($matchingRoutes);
            if ($app1RouteId !== null) {
                $matchedWithParentId += count($matchingRoutes);
            }

            foreach ($matchingRoutes as $route) {
                $currentParentId = $route->parent_hiking_route_id;
                $willUpdateOsmfeaturesId = true;
                $willUpdateParentId = ($app1RouteId !== null && (int) $currentParentId !== (int) $app1RouteId);

                // Se ha un parent (trovato da osmid) copia osm2cai_status, validation_date, validator_id e osmfeatures_data dalla hiking route app_1
                $willUpdateOsm2caiStatus = false;
                $parentOsm2caiStatus = null;
                if ($app1Route !== null && $app1Route->osm2cai_status !== null) {
                    $parentOsm2caiStatus = $app1Route->osm2cai_status;
                    if ($route->osm2cai_status !== $parentOsm2caiStatus) {
                        $willUpdateOsm2caiStatus = true;
                    }
                }

                $willUpdateValidationDate = false;
                $parentValidationDate = null;
                if ($app1Route !== null && $app1Route->validation_date !== null) {
                    $parentValidationDate = $app1Route->validation_date;
                    $routeValDate = $route->validation_date !== null ? substr((string) $route->validation_date, 0, 10) : null;
                    $parentValDate = substr((string) $parentValidationDate, 0, 10);
                    if ($routeValDate !== $parentValDate) {
                        $willUpdateValidationDate = true;
                    }
                }

                $willUpdateValidatorId = false;
                $parentValidatorId = null;
                if ($app1Route !== null && $app1Route->validator_id !== null) {
                    $parentValidatorId = $app1Route->validator_id;
                    if ((int) $route->validator_id !== (int) $parentValidatorId) {
                        $willUpdateValidatorId = true;
                    }
                }

                $willUpdateOsmfeaturesData = false;
                $parentOsmfeaturesData = null;
                if ($app1Route !== null && $app1Route->osmfeatures_data !== null) {
                    $parentOsmfeaturesData = $app1Route->osmfeatures_data;
                    $currentOsmfeaturesData = $route->osmfeatures_data;
                    if ($currentOsmfeaturesData !== $parentOsmfeaturesData) {
                        $willUpdateOsmfeaturesData = true;
                    }
                }

                $willUpdate = $willUpdateOsmfeaturesId || $willUpdateParentId || $willUpdateOsm2caiStatus || $willUpdateValidationDate || $willUpdateValidatorId || $willUpdateOsmfeaturesData;

                $routeName = $route->name ?? 'N/A';
                $osmidToShow = ! empty($osmid) ? (string) $osmid : null;
                if ($osmidToShow === null && $route->osmfeatures_data && isset($route->osmfeatures_data['properties']['osm_id'])) {
                    $osmidToShow = (string) $route->osmfeatures_data['properties']['osm_id'];
                }
                if ($osmidToShow === null && $currentParentId !== null) {
                    $parentRoute = HikingRoute::find($currentParentId);
                    if ($parentRoute && $parentRoute->osmfeatures_data && isset($parentRoute->osmfeatures_data['properties']['osm_id'])) {
                        $osmidToShow = (string) $parentRoute->osmfeatures_data['properties']['osm_id'];
                    }
                }

                $parentIdToShow = $app1RouteId ?? $currentParentId;
                $osm2caiStatusToShow = $parentOsm2caiStatus ?? $route->osm2cai_status;
                $validationDateToShow = $parentValidationDate ?? $route->validation_date;
                $validatorIdToShow = $parentValidatorId ?? $route->validator_id;

                $osmidValue = $osmidToShow !== null ? (string) $osmidToShow : 'null';
                $parentIdValue = $parentIdToShow !== null ? (string) $parentIdToShow : 'null';
                $osm2caiStatusValue = $osm2caiStatusToShow !== null ? (string) $osm2caiStatusToShow : 'null';
                $validationDateValue = $validationDateToShow !== null ? substr((string) $validationDateToShow, 0, 10) : 'null';
                $validatorIdValue = $validatorIdToShow !== null ? (string) $validatorIdToShow : 'null';

                $outputLine = implode(', ', [
                    "name: {$routeName}",
                    "osmid: {$osmidValue}",
                    "parent_hiking_route_id: {$parentIdValue}",
                    "osm2cai_status: {$osm2caiStatusValue}",
                    "validation_date: {$validationDateValue}",
                    "validator_id: {$validatorIdValue}",
                ]);

                if ($willUpdate) {
                    if ($dryRun) {
                        $this->comment($outputLine . ' [DRY-RUN]');
                    } else {
                        $this->info($outputLine);
                    }

                    if (! $dryRun) {
                        $updateData = ['osmfeatures_id' => $osmfeaturesId];
                        if ($willUpdateParentId) {
                            $updateData['parent_hiking_route_id'] = $app1RouteId;
                        }
                        if ($willUpdateOsm2caiStatus && $parentOsm2caiStatus !== null) {
                            $updateData['osm2cai_status'] = $parentOsm2caiStatus;
                        }
                        if ($willUpdateValidationDate && $parentValidationDate !== null) {
                            $updateData['validation_date'] = $parentValidationDate;
                        }
                        if ($willUpdateValidatorId && $parentValidatorId !== null) {
                            $updateData['validator_id'] = $parentValidatorId;
                        }
                        if ($willUpdateOsmfeaturesData && $parentOsmfeaturesData !== null) {
                            $updateData['osmfeatures_data'] = $parentOsmfeaturesData;
                        }

                        // Merge dei properties esistenti della hikingroute con i properties provenienti dalla feature Sicai
                        $currentProperties = $route->properties ?? [];
                        $newProperties = $feature['properties'] ?? [];
                        if (! empty($newProperties)) {
                            $updateData['properties'] = array_merge($currentProperties, $newProperties);
                        }

                        $route->updateQuietly($updateData);
                        $updated++;
                    }
                } else {
                    $this->line($outputLine . ' [nessuna modifica]');
                }
            }
        }

        $this->newLine();
        $this->info('Lettura punti accoglienza da pt_accoglienza_unofficial (solo lettura DB Sicai)...');

        try {
            $accoglienzaRows = $this->fetchAccoglienzaPoisFromSicaiDb();
        } catch (\Throwable $e) {
            $this->error('Errore lettura pt_accoglienza_unofficial: ' . $e->getMessage());
            $accoglienzaRows = [];
        }

        $ecPoisStats = $this->syncAccoglienzaPois($accoglienzaRows, $dryRun);

        $this->newLine();
        $this->info('Import immagini per EC POI (pt_accoglienza_unofficial)...');
        $ecPoisImagesStats = $this->syncAccoglienzaPoiImages($accoglienzaRows, $dryRun);

        $this->newLine();
        $this->info('=== Riepilogo HikingRoute ===');

        if ($dryRun) {
            $this->table(
                ['Esito', 'Numero'],
                [
                    ['Match trovati (con osmid)', $matchedWithOsmId],
                    ['Match con parent_id', $matchedWithParentId],
                    ['Saltati (senza osmid)', $skipped],
                ]
            );
        } else {
            $this->table(
                ['Esito', 'Numero'],
                [
                    ['Match trovati (con osmid)', $matchedWithOsmId],
                    ['Match con parent_id', $matchedWithParentId],
                    ['Saltati (senza osmid)', $skipped],
                ]
            );
            $this->info("Operazione completata. Aggiornati: {$updated} route.");
        }

        $this->newLine();
        $this->info('=== Riepilogo EC Pois (pt_accoglienza_unofficial) ===');

        $this->table(
            ['Esito', 'Numero'],
            [
                ['Righe lette', $ecPoisStats['rows_total'] ?? 0],
                ['EC creati', $ecPoisStats['created'] ?? 0],
                ['EC aggiornati', $ecPoisStats['updated'] ?? 0],
                ['Righe senza tappa01/02/03', $ecPoisStats['rows_without_tappe'] ?? 0],
                ['Righe senza match HikingRoute', $ecPoisStats['rows_without_route_match'] ?? 0],
                ['Relazioni HikingRoute-EC create (conteggio route collegate)', $ecPoisStats['linked_routes'] ?? 0],
                ['Righe con immagini', $ecPoisImagesStats['rows_with_images'] ?? 0],
                ['Righe senza immagini', $ecPoisImagesStats['rows_without_images'] ?? 0],
                ['Righe senza EC POI corrispondente', $ecPoisImagesStats['rows_without_poi'] ?? 0],
                ['Immagini totali trovate', $ecPoisImagesStats['images_total'] ?? 0],
                ['Immagini importate', $ecPoisImagesStats['images_attached'] ?? 0],
                ['Immagini già presenti (saltate)', $ecPoisImagesStats['images_skipped_existing'] ?? 0],
            ]
        );

        if ($dryRun) {
            $this->warn('Nessuna modifica scritta: eseguire senza --dry-run per applicare.');
        }

        Log::channel('single')->info('osm2cai:populate-osmfeatures-id-from-sicai-db', [
            'dry_run' => $dryRun,
            'limit' => $limit,
            'matched_with_osmid' => $matchedWithOsmId,
            'matched_with_parent_id' => $matchedWithParentId,
            'updated_routes' => $updated,
            'skipped_routes' => $skipped,
            'ec_pois_rows_total' => $ecPoisStats['rows_total'] ?? 0,
            'ec_pois_created' => $ecPoisStats['created'] ?? 0,
            'ec_pois_updated' => $ecPoisStats['updated'] ?? 0,
            'ec_pois_rows_without_tappe' => $ecPoisStats['rows_without_tappe'] ?? 0,
            'ec_pois_rows_without_route_match' => $ecPoisStats['rows_without_route_match'] ?? 0,
            'ec_pois_linked_routes' => $ecPoisStats['linked_routes'] ?? 0,
        ]);

        return self::SUCCESS;
    }

    /**
     * Legge le "feature" dal DB Sicai (solo SELECT). Nessuna scrittura su sicai_postgis.
     * Tabelle: SI_Tappe e SICAI_MTB. Restituisce array in formato compatibile con il comando API.
     */
    private function fetchFeaturesFromSicaiDb(): array
    {
        $conn = DB::connection('sicai_postgis');

        $features = [];

        // SI_Tappe: tutti i campi; per il match usiamo tappa e openstreetmap (colonna openstreetmap)
        $rowsSiTappe = $conn->select('SELECT * FROM "SI_Tappe" WHERE "tappa" IS NOT NULL');
        foreach ($rowsSiTappe as $row) {
            $osmid = $row->openstreetmap ?? null;
            if ($osmid !== null && $osmid !== '') {
                $sicaiProperties = [
                    'data' => $row->data,
                    'tappa' => $row->tappa,
                    'referente' => ['name' => $row->referente ?? null, 'email' => $row->email ?? null],
                    'stazioni' => ['treno' => $row->stazione_treno == 'si' ? true : false, 'bus' => $row->stazione_bus == 'si' ? true : false],
                    'parcheggio' => $row->parcheggio == 'si' ? true : false,
                    'pt_accoglienza' => $row->pt_accoglienza == 'si' ? true : false,
                    'email_ref_regionale' => $row->email_ref_regionale ?? null,
                    'percorribilità' => $row->percorribilità,
                    'segnaletica' => $row->segnaletica,
                    'descrizione' => $row->descrizione,
                    'verifica' => $row->verifica,
                    'note' => $row->Note,
                    'segnalazioni' => $row->Segnalazioni,
                    'sezione' => $row->sezione,
                    'referente_regionale' => $row->referente_regionale,
                    'sezione_ref_regionale' => $row->sezione_ref_regionale,
                    'email_ref_regionale' => $row->email_ref_regionale,
                    'sezioni_manutenzione' => $row->sezioni_manutenzione,

                ];
                $features[] = [
                    'source' => 'si_tappe',
                    'properties' => [
                        'osmid' => (string) $osmid,
                        'description' => ['it' => $row->descrizione_sito],
                        'sicai' => $sicaiProperties,
                    ],
                    'raw' => (array) $row,
                ];
            }
        }

        // SICAI_MTB: tutti i campi; per il match usiamo tappa e osmid
        $rowsMtb = $conn->select('SELECT * FROM "SICAI_MTB" WHERE "tappa" IS NOT NULL');
        foreach ($rowsMtb as $row) {
            $osmid = $row->osmid ?? null;
            if ($osmid !== null && $osmid !== '') {
                $features[] = [
                    'source' => 'sicai_mtb',
                    'properties' => [
                        'osmid' => (string) $osmid,
                        'sicai' => [
                            'tappa' => $row->tappa,
                        ],
                    ],
                    'raw' => (array) $row,
                ];
            }
        }

        return $features;
    }

    private function getRefFromRoute(HikingRoute $route): ?string
    {
        if ($route->properties && isset($route->properties['ref'])) {
            $ref = $route->properties['ref'];
            if (! empty($ref)) {
                return (string) $ref;
            }
        }
        if ($route->osmfeatures_data && isset($route->osmfeatures_data['properties']['ref'])) {
            $ref = $route->osmfeatures_data['properties']['ref'];
            if (! empty($ref)) {
                return (string) $ref;
            }
        }

        return null;
    }

    private function getOsmIdFromRoute(HikingRoute $route): ?string
    {
        if ($route->osmfeatures_data && isset($route->osmfeatures_data['properties']['osm_id'])) {
            $osmId = $route->osmfeatures_data['properties']['osm_id'];
            if (! empty($osmId)) {
                return (string) $osmId;
            }
        }

        return null;
    }

    /**
     * Legge i punti accoglienza dalla tabella pt_accoglienza_unofficial del DB Sicai.
     * Nessuna scrittura su sicai_postgis.
     *
     * Ogni elemento restituito contiene:
     * - sicai: array con tutti i campi della tabella + chiave 'dbtable'
     * - tappe: elenco dei valori di tappa01/02/03 normalizzati
     * - raw: riga originale convertita in array
     */
    private function fetchAccoglienzaPoisFromSicaiDb(): array
    {
        $conn = DB::connection('sicai_postgis');

        // Nessuna manipolazione sulla geometry: solo lettura grezza (SELECT *).
        $geomColumn = $this->getSicaiGeometryColumnName($conn);
        $rows = $conn->select('SELECT * FROM "pt_accoglienza_unofficial"');
        $items = [];

        foreach ($rows as $row) {
            $raw = (array) $row;

            $sicai = $raw;
            $sicai['dbtable'] = 'pt_accoglienza_unofficial';

            $tappe = [];
            foreach (['tappa01', 'tappa02', 'tappa03'] as $col) {
                if (! empty($raw[$col] ?? null)) {
                    $value = trim((string) $raw[$col]);
                    if ($value !== '') {
                        $tappe[] = $value;
                    }
                }
            }

            $items[] = [
                'sicai' => $sicai,
                'tappe' => $tappe,
                'raw' => $raw,
                'geometry_column' => $geomColumn,
            ];
        }

        return $items;
    }

    /**
     * Sincronizza i punti accoglienza Sicai con la tabella ec_pois (app_id = 2).
     *
     * - Crea/aggiorna EcPoi con app_id = 2
     * - Salva tutti i campi Sicai in properties['sicai'], inclusa la chiave 'dbtable'
     * - Collega gli EcPoi alle HikingRoute (app_id = 2) in base alle colonne tappa01/02/03 (match esatto su name)
     * - In modalità dry-run non effettua scritture, ma calcola comunque le statistiche.
     *
     * @return array{
     *     rows_total:int,
     *     created:int,
     *     updated:int,
     *     rows_without_tappe:int,
     *     rows_without_route_match:int,
     *     linked_routes:int
     * }
     */
    private function syncAccoglienzaPois(array $rows, bool $dryRun): array
    {
        $rowsTotal = count($rows);
        $created = 0;
        $updated = 0;
        $rowsWithoutTappe = 0;
        $rowsWithoutRouteMatch = 0;
        $linkedRoutes = 0;

        if ($rowsTotal === 0) {
            $this->warn('Nessuna riga trovata in pt_accoglienza_unofficial.');

            return [
                'rows_total' => 0,
                'created' => 0,
                'updated' => 0,
                'rows_without_tappe' => 0,
                'rows_without_route_match' => 0,
                'linked_routes' => 0,
            ];
        }

        $this->info("Trovate {$rowsTotal} righe in pt_accoglienza_unofficial.");

        $puntoAccoglienzaType = $this->getOrCreatePuntoAccoglienzaTaxonomyPoiType($dryRun);
        $sentieroItaliaUser = $this->getSentieroItaliaCaiUser($dryRun);

        foreach ($rows as $index => $row) {
            $sicai = $row['sicai'] ?? [];
            $tappe = $row['tappe'] ?? [];
            $raw = $row['raw'] ?? [];

            $externalId = null;
            if (isset($raw['id'])) {
                $externalId = (string) $raw['id'];
            } elseif (isset($raw['gid'])) {
                $externalId = (string) $raw['gid'];
            }

            $sourceKey = $externalId !== null
                ? 'pt_accoglienza_unofficial:' . $externalId
                : 'pt_accoglienza_unofficial:row_' . ($index + 1);

            // Usa sourceKey solo dentro properties->sicai->source_key per identificare univocamente il record
            $query = EcPoi::query()
                ->where('app_id', 2)
                ->where('properties->sicai->source_key', $sourceKey);

            /** @var EcPoi|null $ecPoi */
            $ecPoi = $query->first();
            $isNew = false;

            if (! $ecPoi) {
                $ecPoi = new EcPoi();
                $ecPoi->app_id = 2;
                $ecPoi->global = false;
                $isNew = true;
            }

            if ($sentieroItaliaUser !== null) {
                $ecPoi->user_id = $sentieroItaliaUser->id;
            }

            $properties = $ecPoi->properties ?? [];
            if (! is_array($properties)) {
                $properties = [];
            }
            // Nome: va in ec_poi->name (translatable), non in properties->sicai
            $name = $raw['name'] ?? $raw['addr:city'] ?? null;
            if ($name !== null && $name !== '') {
                $ecPoi->setTranslation('name', 'it', $name);
                $properties['name'] = ['it' => $name];
            }

            // Descrizione: va in properties->description (in EcPoi non esiste il campo description), non in properties->sicai
            $description = $raw['Descrizione'] ?? $raw['descrizione'] ?? null;
            if ($description !== null && $description !== '') {
                $properties['description'] = ['it' => $description];
            }

            // properties->sicai: tutti i campi della tabella tranne name e Descrizione
            unset($sicai['name'], $sicai['description'], $sicai['Descrizione'], $sicai['descrizione']);
            $sicai['source_key'] = $sourceKey;
            $properties['sicai'] = $sicai;
            $ecPoi->properties = $properties;

            $canLinkToRoutes = isset($sicai['situazione']) && $sicai['situazione'] === 'ha aderito';
            if ($canLinkToRoutes) {
                $ecPoi->global = true;
            }

            // Geometria: valore grezzo da raw (colonna geom/geometry); trasformazione 3857→4326 tramite GeometryService (sul nostro DB)
            $geometryColumn = $row['geometry_column'] ?? null;
            $rawGeometry = $geometryColumn !== null ? ($raw[$geometryColumn] ?? null) : null;
            if ($rawGeometry !== null && $rawGeometry !== '') {
                $geometryStr = is_string($rawGeometry) ? trim($rawGeometry) : (string) $rawGeometry;
                if ($geometryStr !== '' && stripos($geometryStr, 'SRID=') !== 0) {
                    $geometryStr = 'SRID=3857;' . $geometryStr;
                }
                try {
                    $geometry4326 = app(GeometryService::class)->geometryTo4326Srid($geometryStr);
                    if ($geometry4326 !== null && $geometry4326 !== '') {
                        $ecPoi->geometry = $geometry4326;
                    }
                } catch (\Throwable $e) {
                    Log::warning('Transform 3857→4326 fallita per EC POI ' . $sourceKey . ': ' . $e->getMessage());
                }
            }

            if ($dryRun) {
                $this->comment(sprintf(
                    'EC POI [%s] %s - %s',
                    $isNew ? 'CREA' : 'AGGIORNA',
                    $sourceKey,
                    $name ?? 'N/D'
                ));
            } else {
                $ecPoi->saveQuietly();
                if ($isNew) {
                    $created++;
                } else {
                    $updated++;
                }
                if ($puntoAccoglienzaType !== null) {
                    $ecPoi->taxonomyPoiTypes()->syncWithoutDetaching([$puntoAccoglienzaType->id]);
                }
            }

            if (empty($tappe)) {
                $rowsWithoutTappe++;

                continue;
            }

            $trimmedTappe = array_values(array_unique(array_filter(array_map(
                static fn($val) => trim((string) $val),
                $tappe
            ))));

            if (empty($trimmedTappe)) {
                $rowsWithoutTappe++;

                continue;
            }

            $routeIds = [];
            foreach ($trimmedTappe as $tappaName) {
                // name è JSON in colonna stringa tipo {"it":"SI A10"} -> estraiamo (name::jsonb)->>'it'
                $normalizedTappa = strtolower(trim(preg_replace('/\s+/', ' ', $tappaName)));
                $ids = HikingRoute::query()
                    ->where('app_id', 2)
                    ->whereRaw("LOWER(REGEXP_REPLACE(TRIM(COALESCE((name::jsonb)->>'it', '')), '\\s+', ' ', 'g')) = ?", [$normalizedTappa])
                    ->pluck('id')
                    ->all();

                if (! empty($ids)) {
                    $routeIds = array_merge($routeIds, $ids);
                } else {
                    $this->line(sprintf(
                        'Nessuna HikingRoute trovata per tappa "%s" (source: %s)',
                        $tappaName,
                        $sourceKey
                    ));
                }
            }

            $routeIds = array_values(array_unique($routeIds));

            if (empty($routeIds)) {
                $rowsWithoutRouteMatch++;

                continue;
            }

            if ($dryRun) {
                if ($canLinkToRoutes) {
                    $this->comment(sprintf(
                        'Collegherei EC POI %s alle HikingRoute id: [%s] [DRY-RUN]',
                        $sourceKey,
                        implode(', ', $routeIds)
                    ));
                } else {
                    $this->comment(sprintf(
                        'NON collegherei EC POI %s alle HikingRoute id: [%s] perché situazione != \"ha aderito\" [DRY-RUN]',
                        $sourceKey,
                        implode(', ', $routeIds)
                    ));
                }
            } else {
                if ($canLinkToRoutes) {
                    $ecPoi->nearbyHikingRoutes()->syncWithoutDetaching($routeIds);
                    $linkedRoutes += count($routeIds);
                }
            }
        }

        return [
            'rows_total' => $rowsTotal,
            'created' => $created,
            'updated' => $updated,
            'rows_without_tappe' => $rowsWithoutTappe,
            'rows_without_route_match' => $rowsWithoutRouteMatch,
            'linked_routes' => $linkedRoutes,
        ];
    }

    /**
     * Importa le immagini dei punti accoglienza usando i campi foto02–foto05.
     *
     * - Per ogni riga di pt_accoglienza_unofficial trova l'EC POI corrispondente (properties->sicai->source_key)
     * - Costruisce l'URL assoluto con il prefix specificato e la path relativa
     * - Importa le immagini nella media collection "default" del POI tramite Spatie
     * - È idempotente: se esiste già un media nella collection "default" con lo stesso filename, salta l'import
     * - In modalità dry-run non effettua scritture ma logga le operazioni che verrebbero eseguite
     *
     * @return array{
     *     rows_total:int,
     *     rows_without_poi:int,
     *     rows_with_images:int,
     *     rows_without_images:int,
     *     images_total:int,
     *     images_attached:int,
     *     images_skipped_existing:int
     * }
     */
    private function syncAccoglienzaPoiImages(array $rows, bool $dryRun): array
    {
        $rowsTotal = count($rows);
        $rowsWithoutPoi = 0;
        $rowsWithImages = 0;
        $rowsWithoutImages = 0;
        $imagesTotal = 0;
        $imagesAttached = 0;
        $imagesSkippedExisting = 0;

        if ($rowsTotal === 0) {
            $this->warn('Nessuna riga trovata in pt_accoglienza_unofficial per import immagini.');

            return [
                'rows_total' => 0,
                'rows_without_poi' => 0,
                'rows_with_images' => 0,
                'rows_without_images' => 0,
                'images_total' => 0,
                'images_attached' => 0,
                'images_skipped_existing' => 0,
            ];
        }

        $baseUrl = 'https://sentieroitaliamappe.cai.it/index.php/view/media/getMedia?repository=sicaipubblico&project=SICAI_Pubblico&path=';
        $photoColumns = ['foto02', 'foto03', 'foto04', 'foto05'];

        foreach ($rows as $index => $row) {
            $raw = $row['raw'] ?? [];
            $sicai = $row['sicai'] ?? $raw;

            $externalId = null;
            if (isset($raw['id'])) {
                $externalId = (string) $raw['id'];
            } elseif (isset($raw['gid'])) {
                $externalId = (string) $raw['gid'];
            }

            $sourceKey = $externalId !== null
                ? 'pt_accoglienza_unofficial:' . $externalId
                : 'pt_accoglienza_unofficial:row_' . ($index + 1);

            /** @var EcPoi|null $ecPoi */
            $ecPoi = EcPoi::query()
                ->where('app_id', 2)
                ->where('properties->sicai->source_key', $sourceKey)
                ->first();

            if (! $ecPoi) {
                $rowsWithoutPoi++;
                $this->line(sprintf(
                    'Nessun EC POI trovato per source_key %s, salto import immagini.',
                    $sourceKey
                ));

                continue;
            }

            $relativePaths = [];
            foreach ($photoColumns as $column) {
                $path = $raw[$column] ?? $sicai[$column] ?? null;
                if (is_string($path)) {
                    $path = trim($path);
                    if ($path !== '') {
                        $relativePaths[] = $path;
                    }
                }
            }

            if (empty($relativePaths)) {
                $rowsWithoutImages++;

                continue;
            }

            $rowsWithImages++;

            foreach ($relativePaths as $relativePath) {
                $imagesTotal++;

                // Codifica solo gli spazi per evitare problemi di URL, mantenendo gli slash
                $pathForUrl = str_replace(' ', '%20', $relativePath);
                $url = $baseUrl . $pathForUrl;
                $fileName = basename($relativePath);

                // Idempotenza: se esiste già un media nella collection "default" con lo stesso filename, salta
                $existingMedia = $ecPoi->media
                    ->where('collection_name', 'default')
                    ->firstWhere('file_name', $fileName);

                if ($existingMedia) {
                    $imagesSkippedExisting++;
                    $this->line(sprintf(
                        'Immagine già presente per EC POI %s (%s), salto: %s',
                        $sourceKey,
                        $ecPoi->id,
                        $relativePath
                    ));

                    continue;
                }

                if ($dryRun) {
                    $this->comment(sprintf(
                        'Importerei immagine per EC POI %s (%s): %s',
                        $sourceKey,
                        $ecPoi->id,
                        $url
                    ));

                    continue;
                }

                try {
                    $ecPoi
                        ->addMediaFromUrl($url)
                        ->usingFileName($fileName)
                        ->usingName($fileName)
                        ->toMediaCollection('default');

                    $imagesAttached++;

                    // Log minimale per dare feedback durante l'import massivo
                    $this->line(sprintf(
                        'Importata immagine per EC POI %s (%s): %s',
                        $sourceKey,
                        $ecPoi->id,
                        $relativePath
                    ));
                } catch (\Throwable $e) {
                    Log::warning('Import immagine per EC POI fallito', [
                        'source_key' => $sourceKey,
                        'ec_poi_id' => $ecPoi->id ?? null,
                        'url' => $url,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return [
            'rows_total' => $rowsTotal,
            'rows_without_poi' => $rowsWithoutPoi,
            'rows_with_images' => $rowsWithImages,
            'rows_without_images' => $rowsWithoutImages,
            'images_total' => $imagesTotal,
            'images_attached' => $imagesAttached,
            'images_skipped_existing' => $imagesSkippedExisting,
        ];
    }

    /**
     * Nome della colonna geometria in pt_accoglienza_unofficial (Sicai).
     * Controlla information_schema; default 'geom' poi 'geometry'.
     */
    private function getSicaiGeometryColumnName(\Illuminate\Database\Connection $conn): string
    {
        $col = $conn->selectOne(
            "SELECT column_name FROM information_schema.columns 
             WHERE table_schema = 'public' AND table_name = 'pt_accoglienza_unofficial' 
             AND udt_name = 'geometry' 
             LIMIT 1"
        );
        if ($col && isset($col->column_name)) {
            return $col->column_name;
        }

        return 'geom';
    }

    /**
     * Restituisce il TaxonomyPoiType "Punto Accoglienza" (icona alpine-hut).
     * Se non esiste e non siamo in dry-run, lo crea.
     */
    private function getOrCreatePuntoAccoglienzaTaxonomyPoiType(bool $dryRun): ?TaxonomyPoiType
    {
        $taxonomyPoiType = TaxonomyPoiType::where('identifier', 'punto-accoglienza')->first();

        if ($taxonomyPoiType !== null) {
            return $taxonomyPoiType;
        }

        if ($dryRun) {
            $this->comment('TaxonomyPoiType "Punto Accoglienza" non presente; in dry-run non viene creato.');

            return null;
        }

        $taxonomyPoiType = TaxonomyPoiType::create([
            'name' => ['it' => 'Punto Accoglienza'],
            'description' => [],
            'excerpt' => [],
            'identifier' => 'punto-accoglienza',
            'icon' => 'txn-alpine-hut',
        ]);
        $this->info('Creato TaxonomyPoiType "Punto Accoglienza" (identifier: punto-accoglienza, icon: alpine-hut).');

        return $taxonomyPoiType;
    }

    /**
     * Restituisce l'utente "Sentiero Italia CAI" (per assegnare user_id ai POI importati da pt_accoglienza_unofficial).
     * Se non esiste, in dry-run logga un commento; altrimenti logga un warning.
     */
    private function getSentieroItaliaCaiUser(bool $dryRun): ?User
    {
        $user = User::where('name', 'Sentiero Italia CAI')->first();

        if ($user !== null) {
            return $user;
        }

        if ($dryRun) {
            $this->comment('Utente "Sentiero Italia CAI" non trovato; in dry-run i POI non avranno user_id.');
        } else {
            Log::warning('Utente "Sentiero Italia CAI" non trovato: i POI pt_accoglienza_unofficial non avranno user_id assegnato.');
        }

        return null;
    }
}
