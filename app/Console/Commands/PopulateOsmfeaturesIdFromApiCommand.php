<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PopulateOsmfeaturesIdFromApiCommand extends Command
{
    protected $signature = 'osm2cai:populate-osmfeatures-id-from-api
                            {--dry-run : Esegue senza scrivere sul database}
                            {--limit= : Processa al massimo N route (utile per test)}';

    protected $description = 'Popola osmfeatures_id delle hikingroute app_2 confrontando properties->tappa delle API con properties->ref delle route.';

    private const API_URL_NORD_SUD = 'https://sentieroitaliamappe.cai.it/index.php/lizmap/service?repository=sicaipubblico&project=SICAI_Pubblico&SERVICE=WFS&VERSION=1.1.0&REQUEST=GetFeature&TYPENAME=SICAI_Ciclo_-_NORD_SUD&OUTPUTFORMAT=application/json&SRSNAME=EPSG:4326';

    private const API_URL_SUD_NORD = 'https://sentieroitaliamappe.cai.it/index.php/lizmap/service?repository=sicaipubblico&project=SICAI_Pubblico&SERVICE=WFS&VERSION=1.1.0&REQUEST=GetFeature&TYPENAME=SICAI_Ciclo_-_SUD_NORD&OUTPUTFORMAT=application/json&SRSNAME=EPSG:4326';

    private const API_URL_SI_TAPPE = 'https://sentieroitaliamappe.cai.it/index.php/lizmap/service?repository=sicaipubblico&project=SICAI_Pubblico&SERVICE=WFS&VERSION=1.1.0&REQUEST=GetFeature&TYPENAME=SI_Tappe&OUTPUTFORMAT=application/json&SRSNAME=EPSG:4326';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        if ($dryRun) {
            $this->warn('Modalità dry-run: nessuna modifica al database.');
        }

        $this->info('Recupero dati dalle API...');

        // Recupera le FeatureCollection dalle API
        $featuresNordSud = $this->fetchApiFeatures(self::API_URL_NORD_SUD);
        $featuresSudNord = $this->fetchApiFeatures(self::API_URL_SUD_NORD);
        $featuresSiTappe = $this->fetchApiFeatures(self::API_URL_SI_TAPPE);

        if ($featuresNordSud === null || $featuresSudNord === null || $featuresSiTappe === null) {
            $this->error('Errore nel recupero dei dati dalle API.');
            return self::FAILURE;
        }

        $totalFeatures = count($featuresNordSud) + count($featuresSudNord) + count($featuresSiTappe);
        $this->info("Trovate {$totalFeatures} feature totali dalle API (" . count($featuresNordSud) . " NORD_SUD + " . count($featuresSudNord) . " SUD_NORD + " . count($featuresSiTappe) . " SI_Tappe).");
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
                if (!isset($hikingRoutesByRef[$ref])) {
                    $hikingRoutesByRef[$ref] = [];
                }
                $hikingRoutesByRef[$ref][] = $route;
            }
        }

        $this->info("Hikingroute mappate per ref: " . count($hikingRoutesByRef) . " ref univoci.");
        $this->newLine();

        // Carica le hikingroute della app_1 con osm_id per lookup veloce
        $this->info('Caricamento hikingroute della app_1 per lookup osm_id...');
        $app1RoutesQuery = HikingRoute::query()
            ->where('app_id', 1)
            ->whereNotNull('osmfeatures_id')
            ->whereRaw("osmfeatures_data->'properties'->>'osm_id' IS NOT NULL");

        $app1Routes = $app1RoutesQuery->get();

        // Crea una mappa per lookup veloce: osm_id => hikingroute app_1
        $app1RoutesByOsmId = [];
        foreach ($app1Routes as $route) {
            $osmId = $this->getOsmIdFromRoute($route);
            if ($osmId !== null) {
                $app1RoutesByOsmId[$osmId] = $route;
            }
        }

        $this->info("Hikingroute app_1 mappate per osm_id: " . count($app1RoutesByOsmId) . " osm_id univoci.");
        $this->newLine();

        // Processa le feature delle API
        $matchedWithOsmId = 0; // Match trovati con osmid presente
        $matchedWithParentId = 0; // Match trovati con parent_id
        $updated = 0;
        $skipped = 0; // Saltati (senza osmid)

        $allFeatures = array_merge($featuresNordSud, $featuresSudNord, $featuresSiTappe);

        $this->info('Inizio confronto e aggiornamento...');
        $this->newLine();

        foreach ($allFeatures as $feature) {
            $tappa = $feature['properties']['tappa'] ?? null;

            if ($tappa === null) {
                $skipped++;
                continue;
            }

            // Cerca le hikingroute con ref corrispondente
            if (!isset($hikingRoutesByRef[$tappa])) {
                $skipped++;
                continue;
            }

            $matchingRoutes = $hikingRoutesByRef[$tappa];

            // Estrai osmid dalle properties (osmid o openstreetmap sono la stessa cosa)
            $osmid = $feature['properties']['osmid'] ?? $feature['properties']['openstreetmap'] ?? null;

            $osmfeaturesId = null;
            $app1RouteId = null; // ID della hikingroute app_1 trovata (se match tramite osmid)
            $app1Route = null; // Oggetto hikingroute app_1 trovata (se match tramite osmid)

            if (!empty($osmid)) {
                // Crea osmfeatures_id concatenando 'R' come prefisso
                $osmid = (string) $osmid;
                $osmfeaturesId = 'R' . $osmid;

                // Cerca nella app_1 per trovare il parent usando osmid
                if (isset($app1RoutesByOsmId[$osmid])) {
                    $app1Route = $app1RoutesByOsmId[$osmid];
                    $app1RouteId = $app1Route->id; // Salva l'ID della hikingroute app_1
                }
            } else {
                // Nessuno dei due campi presente
                $skipped++;
                continue;
            }

            // Conta i match trovati nelle API (quando ho osmid o openstreetmap)
            // Questo è un match trovato perché ho trovato tappa che matcha con ref
            $matchedWithOsmId += count($matchingRoutes);

            // Se ho trovato parent_id tramite osmid, conta anche quello
            if ($app1RouteId !== null) {
                $matchedWithParentId += count($matchingRoutes);
            }

            foreach ($matchingRoutes as $route) {
                $currentParentId = $route->parent_hiking_route_id;
                // Aggiorna sempre quando abbiamo un osmfeatures_id da inserire
                $willUpdateOsmfeaturesId = true; // Sempre aggiorniamo quando abbiamo osmfeatures_id
                $willUpdateParentId = ($app1RouteId !== null && (int) $currentParentId !== (int) $app1RouteId);
                
                // Se c'è un parent, verifica se devo aggiornare osm2cai_status
                $willUpdateOsm2caiStatus = false;
                $parentOsm2caiStatus = null;
                if ($app1Route !== null && $app1Route->osm2cai_status !== null) {
                    $parentOsm2caiStatus = $app1Route->osm2cai_status;
                    // Aggiorna se il valore è diverso da quello attuale del child
                    if ($route->osm2cai_status !== $parentOsm2caiStatus) {
                        $willUpdateOsm2caiStatus = true;
                    }
                }
                
                // Se c'è un parent, verifica se devo aggiornare osmfeatures_data
                $willUpdateOsmfeaturesData = false;
                $parentOsmfeaturesData = null;
                if ($app1Route !== null && $app1Route->osmfeatures_data !== null) {
                    $parentOsmfeaturesData = $app1Route->osmfeatures_data;
                    // Aggiorna se i dati sono diversi (confronto semplice tra array)
                    $currentOsmfeaturesData = $route->osmfeatures_data;
                    if ($currentOsmfeaturesData !== $parentOsmfeaturesData) {
                        $willUpdateOsmfeaturesData = true;
                    }
                }
                
                $willUpdate = $willUpdateOsmfeaturesId || $willUpdateParentId || $willUpdateOsm2caiStatus || $willUpdateOsmfeaturesData;

                // Prepara i dati per l'output schematico
                $routeName = $route->name ?? 'N/A';

                // Determina osmid da mostrare (dalla feature, dalla route stessa, o dal parent esistente)
                $osmidToShow = null;

                // Prima priorità: osmid dalla feature (se presente, anche quando viene da openstreetmap)
                if (!empty($osmid)) {
                    $osmidToShow = (string) $osmid;
                }
                // Seconda priorità: osmid dalla route stessa
                elseif ($route->osmfeatures_data && isset($route->osmfeatures_data['properties']['osm_id'])) {
                    $osmidToShow = (string) $route->osmfeatures_data['properties']['osm_id'];
                }
                // Terza priorità: osmid dal parent esistente
                elseif ($currentParentId !== null) {
                    $parentRoute = HikingRoute::find($currentParentId);
                    if ($parentRoute && $parentRoute->osmfeatures_data && isset($parentRoute->osmfeatures_data['properties']['osm_id'])) {
                        $osmidToShow = (string) $parentRoute->osmfeatures_data['properties']['osm_id'];
                    }
                }

                // Determina parent_hiking_route_id da mostrare
                $parentIdToShow = null;
                if ($app1RouteId !== null) {
                    // Nuovo parent trovato tramite osmid
                    $parentIdToShow = $app1RouteId;
                } elseif ($currentParentId !== null) {
                    // Parent esistente
                    $parentIdToShow = $currentParentId;
                }

                // Determina osm2cai_status da mostrare
                $osm2caiStatusToShow = null;
                if ($parentOsm2caiStatus !== null) {
                    // Status dal parent trovato
                    $osm2caiStatusToShow = $parentOsm2caiStatus;
                } elseif ($route->osm2cai_status !== null) {
                    // Status corrente del child
                    $osm2caiStatusToShow = $route->osm2cai_status;
                }

                // Costruisci output con sempre name, osmid, parent_hiking_route_id, osm2cai_status
                $osmidValue = $osmidToShow !== null ? (string) $osmidToShow : 'null';
                $parentIdValue = $parentIdToShow !== null ? (string) $parentIdToShow : 'null';
                $osm2caiStatusValue = $osm2caiStatusToShow !== null ? (string) $osm2caiStatusToShow : 'null';

                $outputParts = [
                    "name: {$routeName}",
                    "osmid: {$osmidValue}",
                    "parent_hiking_route_id: {$parentIdValue}",
                    "osm2cai_status: {$osm2caiStatusValue}",
                ];

                // Visualizza il risultato in formato compatto
                $outputLine = implode(', ', $outputParts);

                if ($willUpdate) {
                    if ($dryRun) {
                        $this->comment($outputLine . " [DRY-RUN]");
                    } else {
                        $this->info($outputLine);
                    }

                    if (!$dryRun) {
                        $updateData = [];

                        // Aggiorna sempre osmfeatures_id quando disponibile
                        $updateData['osmfeatures_id'] = $osmfeaturesId;

                        if ($willUpdateParentId) {
                            $updateData['parent_hiking_route_id'] = $app1RouteId;
                        }

                        // Se c'è un parent, aggiorna anche osm2cai_status del child con il valore del padre
                        if ($willUpdateOsm2caiStatus && $parentOsm2caiStatus !== null) {
                            $updateData['osm2cai_status'] = $parentOsm2caiStatus;
                        }

                        // Se c'è un parent, aggiorna anche osmfeatures_data del child con il valore del padre
                        if ($willUpdateOsmfeaturesData && $parentOsmfeaturesData !== null) {
                            $updateData['osmfeatures_data'] = $parentOsmfeaturesData;
                        }

                        $route->updateQuietly($updateData);
                        $updated++;
                    }
                } else {
                    $this->line($outputLine . " [nessuna modifica]");
                }
            }
        }

        // Riepilogo finale
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

        Log::channel('single')->info('osm2cai:populate-osmfeatures-id-from-api', [
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
     * Recupera le feature da un'API
     */
    private function fetchApiFeatures(string $url): ?array
    {
        try {
            $response = Http::timeout(60)->get($url);

            if (!$response->successful()) {
                $this->error("Errore nella chiamata API: {$response->status()}");
                return null;
            }

            $data = $response->json();

            if (!isset($data['features']) || !is_array($data['features'])) {
                $this->error("Formato risposta API non valido: features non trovato.");
                return null;
            }

            return $data['features'];
        } catch (\Exception $e) {
            $this->error("Eccezione durante la chiamata API: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Estrae il campo ref da una hikingroute
     * Prova prima in properties->ref, poi in osmfeatures_data->properties->ref
     */
    private function getRefFromRoute(HikingRoute $route): ?string
    {
        // Prova prima in properties->ref
        if ($route->properties && isset($route->properties['ref'])) {
            $ref = $route->properties['ref'];
            if (!empty($ref)) {
                return (string) $ref;
            }
        }

        // Prova in osmfeatures_data->properties->ref
        if ($route->osmfeatures_data && isset($route->osmfeatures_data['properties']['ref'])) {
            $ref = $route->osmfeatures_data['properties']['ref'];
            if (!empty($ref)) {
                return (string) $ref;
            }
        }

        return null;
    }

    /**
     * Estrae il campo osm_id da una hikingroute della app_1
     * Cerca in osmfeatures_data->properties->osm_id
     */
    private function getOsmIdFromRoute(HikingRoute $route): ?string
    {
        if ($route->osmfeatures_data && isset($route->osmfeatures_data['properties']['osm_id'])) {
            $osmId = $route->osmfeatures_data['properties']['osm_id'];
            if (!empty($osmId)) {
                return (string) $osmId;
            }
        }

        return null;
    }
}
