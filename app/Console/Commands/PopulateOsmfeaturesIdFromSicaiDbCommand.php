<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            $tappa = $feature['properties']['sicai_properties']['tappa'] ?? $feature['properties']['tappa'] ?? null;

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

                // Se ha un parent (trovato da osmid) copia osm2cai_status e osmfeatures_data dalla hiking route app_1
                $willUpdateOsm2caiStatus = false;
                $parentOsm2caiStatus = null;
                if ($app1Route !== null && $app1Route->osm2cai_status !== null) {
                    $parentOsm2caiStatus = $app1Route->osm2cai_status;
                    if ($route->osm2cai_status !== $parentOsm2caiStatus) {
                        $willUpdateOsm2caiStatus = true;
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

                $willUpdate = $willUpdateOsmfeaturesId || $willUpdateParentId || $willUpdateOsm2caiStatus || $willUpdateOsmfeaturesData;

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

                $osmidValue = $osmidToShow !== null ? (string) $osmidToShow : 'null';
                $parentIdValue = $parentIdToShow !== null ? (string) $parentIdToShow : 'null';
                $osm2caiStatusValue = $osm2caiStatusToShow !== null ? (string) $osm2caiStatusToShow : 'null';

                $outputLine = implode(', ', [
                    "name: {$routeName}",
                    "osmid: {$osmidValue}",
                    "parent_hiking_route_id: {$parentIdValue}",
                    "osm2cai_status: {$osm2caiStatusValue}",
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
        $this->info('=== Riepilogo ===');

        if ($dryRun) {
            $this->table(
                ['Esito', 'Numero'],
                [
                    ['Match trovati (con osmid)', $matchedWithOsmId],
                    ['Match con parent_id', $matchedWithParentId],
                    ['Saltati (senza osmid)', $skipped],
                ]
            );
            $this->warn('Nessuna modifica scritta: eseguire senza --dry-run per applicare.');
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

        Log::channel('single')->info('osm2cai:populate-osmfeatures-id-from-sicai-db', [
            'dry_run' => $dryRun,
            'limit' => $limit,
            'matched_with_osmid' => $matchedWithOsmId,
            'matched_with_parent_id' => $matchedWithParentId,
            'updated' => $updated,
            'skipped' => $skipped,
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
                        'sicai_properties' => $sicaiProperties,
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
                        'sicai_properties' => [
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
}
