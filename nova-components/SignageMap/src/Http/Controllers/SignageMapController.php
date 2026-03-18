<?php

namespace Osm2cai\SignageMap\Http\Controllers;

use App\Http\Clients\NominatimClient;
use App\Models\HikingRoute;
use App\Models\Poles;
use App\Models\SignageProject;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Wm\WmPackage\Http\Clients\DemClient;

class SignageMapController
{
    /**
     * Suggerisce un nome località per un palo usando Nominatim reverse geocoding
     */
    public function suggestPlaceName(int $poleId): JsonResponse
    {
        $pole = Poles::find($poleId);

        if (! $pole) {
            return response()->json(['error' => 'Pole not found'], 404);
        }

        // Estrai le coordinate dal palo
        $coordinates = DB::table('poles')
            ->where('id', $poleId)
            ->selectRaw('ST_X(geometry::geometry) as lon, ST_Y(geometry::geometry) as lat')
            ->first();

        if (! $coordinates || ! $coordinates->lat || ! $coordinates->lon) {
            return response()->json(['error' => 'Could not extract coordinates from pole'], 400);
        }

        try {
            $nominatim = new NominatimClient;
            $result = $nominatim->reverseGeocode((float) $coordinates->lat, (float) $coordinates->lon);

            // Estrai il nome più appropriato dalla risposta di Nominatim
            $suggestedName = $this->extractPlaceNameFromNominatim($result);

            return response()->json([
                'success' => true,
                'suggestedName' => $suggestedName,
                'nominatimData' => $result,
            ]);
        } catch (Exception $e) {
            Log::warning('Nominatim reverse geocoding failed', [
                'pole_id' => $poleId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Nominatim request failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Estrae il nome località più appropriato dalla risposta di Nominatim
     */
    private function extractPlaceNameFromNominatim(array $nominatimData): string
    {
        // Priorità: name > address.hamlet > address.village > address.suburb > address.town > address.city
        if (! empty($nominatimData['name'])) {
            return $nominatimData['name'];
        }

        $address = $nominatimData['address'] ?? [];

        // Ordine di priorità per località
        $priorities = ['hamlet', 'village', 'suburb', 'town', 'city', 'municipality'];

        foreach ($priorities as $key) {
            if (! empty($address[$key])) {
                return $address[$key];
            }
        }

        // Fallback al display_name troncato
        if (! empty($nominatimData['display_name'])) {
            $parts = explode(',', $nominatimData['display_name']);

            return trim($parts[0]);
        }

        return '';
    }

    /**
     * Aggiorna le properties dell'hikingRoute aggiungendo/rimuovendo checkpoint
     * e salva il name nelle properties del Pole
     */
    public function updateProperties(Request $request, int $id): JsonResponse
    {
        // Trova il record HikingRoute
        $hikingRoute = HikingRoute::findOrFail($id);

        // Ottieni le properties attuali
        $properties = $hikingRoute->properties ?? [];

        // Assicurati che signage esista e che checkpoint sia un array
        if (! isset($properties['signage']) || ! is_array($properties['signage'])) {
            $properties['signage'] = [];
        }
        if (! isset($properties['signage']['checkpoint']) || ! is_array($properties['signage']['checkpoint'])) {
            $properties['signage']['checkpoint'] = [];
        }
        if (! isset($properties['signage']['export_ignore']) || ! is_array($properties['signage']['export_ignore'])) {
            $properties['signage']['export_ignore'] = [];
        }

        // Ottieni l'ID del palo, l'azione (add/remove), name, description e flag export_ignore
        $poleId = $request->input('poleId');
        $add = $request->boolean('add');
        $name = $request->input('name');
        $description = $request->input('description');
        $exportIgnore = $request->boolean('export_ignore');

        if ($poleId === null) {
            return response()->json(['error' => 'poleId is required'], 400);
        }

        $poleId = (int) $poleId;

        // Aggiungi o rimuovi l'ID del palo dall'array checkpoint
        if ($add) {
            // Aggiungi l'ID se non è già presente (confronta sia come intero che come stringa)
            $exists = false;
            foreach ($properties['signage']['checkpoint'] as $existingId) {
                if ((int) $existingId === $poleId || (string) $existingId === (string) $poleId) {
                    $exists = true;
                    break;
                }
            }
            if (! $exists) {
                $properties['signage']['checkpoint'][] = $poleId;
            }
        } else {
            // Rimuovi l'ID se presente (confronta sia come intero che come stringa)
            $properties['signage']['checkpoint'] = array_values(array_filter($properties['signage']['checkpoint'], function ($id) use ($poleId) {
                return (int) $id !== $poleId && (string) $id !== (string) $poleId;
            }));
        }

        // Aggiorna export_ignore: se true aggiungi il palo alla lista (non esportare nell'export CSV/Excel), se false rimuovilo
        if ($exportIgnore) {
            $exists = false;
            foreach ($properties['signage']['export_ignore'] as $existingId) {
                if ((int) $existingId === $poleId || (string) $existingId === (string) $poleId) {
                    $exists = true;
                    break;
                }
            }
            if (! $exists) {
                $properties['signage']['export_ignore'][] = $poleId;
            }
        } else {
            $properties['signage']['export_ignore'] = array_values(array_filter($properties['signage']['export_ignore'], function ($id) use ($poleId) {
                return (int) $id !== $poleId && (string) $id !== (string) $poleId;
            }));
        }

        // Salva name e description nelle properties del Pole se forniti
        if ($add && ($name !== null || $description !== null)) {
            $pole = Poles::find($poleId);
            if ($pole) {
                $poleProperties = $pole->properties ?? [];
                if ($name !== null) {
                    $pole->name = $name;
                }
                if ($description !== null) {
                    $poleProperties['description'] = $description;
                } else {
                    unset($poleProperties['description']);
                }
                $pole->properties = $poleProperties;
                $pole->saveQuietly();
            }
        }

        // Salva le properties aggiornate
        $hikingRoute->properties = $properties;
        $hikingRoute->saveQuietly();

        // Ottieni il GeoJSON e chiama il DEM per arricchire con point matrix
        $geojson = null;
        try {
            $geojson = $hikingRoute->getFeatureCollectionMap(false);
            $geojson = $this->filterLineFeaturesWithOsmfeaturesId($geojson);
            $demClient = app(DemClient::class);
            $geojson = $demClient->getPointMatrix($geojson);

            // Estrai pointFeaturesMap e points_order dal GeoJSON DEM
            $pointFeaturesMap = [];
            $pointsOrder = null;
            foreach ($geojson['features'] ?? [] as $feature) {
                $geometryType = $feature['geometry']['type'] ?? null;

                if ($geometryType === 'Point') {
                    $pointId = (string) ($feature['properties']['id'] ?? null);
                    if ($pointId) {
                        $pointFeaturesMap[$pointId] = $feature;
                    }
                } elseif ($geometryType === 'MultiLineString' && $pointsOrder === null) {
                    $pointsOrder = $feature['properties']['dem']['points_order'] ?? null;
                }
            }

            // Salva points_order in properties->dem->points_order se disponibile
            if ($pointsOrder && is_array($pointsOrder)) {
                if (! isset($properties['dem']) || ! is_array($properties['dem'])) {
                    $properties['dem'] = [];
                }
                $properties['dem']['points_order'] = $pointsOrder;
            }

            $ref = $hikingRoute->osmfeatures_data['properties']['osm_tags']['ref'] ?? '';
            // Calcola le direzioni usando pointFeaturesMap e pointsOrder (properties viene aggiornata by-ref)
            $this->processPointDirections($pointFeaturesMap, $pointsOrder, $properties, $id, $ref);
        } catch (Exception $e) {
            Log::warning('DEM point matrix enrichment failed', [
                'hiking_route_id' => $id,
                'error' => $e->getMessage(),
            ]);
            // In caso di errore, continuiamo comunque senza i dati DEM
        }

        // Salva le properties aggiornate (potrebbero essere state modificate da processPointDirections)
        $hikingRoute->properties = $properties;
        $hikingRoute->saveQuietly();

        // Ricarica il palo per restituire la segnaletica aggiornata (così il frontend può aggiornare le frecce senza chiudere il popup)
        $pole = Poles::find($poleId);
        $poleSignage = $pole ? ($pole->properties['signage'] ?? []) : [];

        return response()->json([
            'success' => true,
            'properties' => $hikingRoute->properties,
            'poleSignage' => $poleSignage,
        ]);
    }

    /**
     * Aggiorna le properties dell'HikingRoute associata a un SignageProject
     * aggiungendo/rimuovendo checkpoint. Trova l'HikingRoute che contiene il palo.
     */
    public function updatePropertiesForSignageProject(Request $request, int $id): JsonResponse
    {
        // Trova il SignageProject
        $signageProject = SignageProject::findOrFail($id);

        // Ottieni l'ID del palo, l'azione (add/remove), name e description
        $poleId = $request->input('poleId');
        $add = $request->boolean('add');
        $name = $request->input('name');
        $description = $request->input('description');

        if ($poleId === null) {
            return response()->json(['error' => 'poleId is required'], 400);
        }

        $poleId = (int) $poleId;

        // Trova il palo
        $pole = Poles::find($poleId);
        if (! $pole) {
            return response()->json(['error' => 'Pole not found'], 404);
        }

        // Trova l'HikingRoute associata che contiene questo palo
        // Usa ST_DWithin come in getPolesWithBuffer per trovare la hiking route corretta
        $hikingRoute = null;
        $hikingRoutes = $signageProject->hikingRoutes()->get();

        foreach ($hikingRoutes as $hr) {
            if (! $hr->geometry) {
                continue;
            }

            // Ottieni la geometria come GeoJSON (come fa getHikingRouteGeojson)
            $geojson = DB::table('hiking_routes')
                ->where('id', $hr->id)
                ->value(DB::raw('ST_AsGeoJSON(geometry)'));

            if (! $geojson) {
                continue;
            }

            // Verifica se il palo è nel buffer della hiking route (10 metri come in getPolesWithBuffer)
            // Usa la stessa query di getPolesWithBuffer
            $poleInBuffer = DB::table('poles')
                ->where('poles.id', $poleId)
                ->whereRaw(
                    'ST_DWithin(poles.geometry, ST_GeomFromGeoJSON(?)::geography, ?)',
                    [$geojson, 500]
                )
                ->exists();

            if ($poleInBuffer) {
                $hikingRoute = $hr;
                break;
            }
        }

        if (! $hikingRoute) {
            return response()->json(['error' => 'HikingRoute containing this pole not found in SignageProject'], 404);
        }

        // Usa lo stesso metodo di updateProperties per aggiornare l'HikingRoute
        // Crea una nuova request con l'ID dell'HikingRoute
        $exportIgnore = $request->boolean('export_ignore');
        $hikingRouteRequest = Request::create(
            "/nova-vendor/signage-map/hiking-route/{$hikingRoute->id}/properties",
            'PATCH',
            [
                'poleId' => $poleId,
                'add' => $add,
                'name' => $name,
                'description' => $description,
                'export_ignore' => $exportIgnore,
            ]
        );

        return $this->updateProperties($hikingRouteRequest, $hikingRoute->id);
    }

    /**
     * Se la hiking route non ha checkpoint, imposta come checkpoint il primo e l'ultimo palo
     * nell'ordine della traccia (points_order da DEM) e ricalcola le direzioni frecce.
     * Usato quando si associa una hiking route a un SignageProject.
     */
    public function setDefaultCheckpointsAndRefreshDirections(HikingRoute $hikingRoute): void
    {
        $properties = $hikingRoute->properties ?? [];
        if (! isset($properties['signage']) || ! is_array($properties['signage'])) {
            $properties['signage'] = [];
        }
        $checkpoints = $properties['signage']['checkpoint'] ?? [];
        if (! is_array($checkpoints)) {
            $checkpoints = [];
        }
        if (! empty($checkpoints)) {
            return;
        }

        try {
            $geojson = $hikingRoute->getFeatureCollectionMap(false);
            $geojson = $this->filterLineFeaturesWithOsmfeaturesId($geojson);
            $demClient = app(DemClient::class);
            $geojson = $demClient->getPointMatrix($geojson);

            $pointFeaturesMap = [];
            $pointsOrder = null;
            foreach ($geojson['features'] ?? [] as $feature) {
                $geometryType = $feature['geometry']['type'] ?? null;
                if ($geometryType === 'Point') {
                    $pointId = (string) ($feature['properties']['id'] ?? null);
                    if ($pointId) {
                        $pointFeaturesMap[$pointId] = $feature;
                    }
                } elseif ($geometryType === 'MultiLineString' && $pointsOrder === null) {
                    $pointsOrder = $feature['properties']['dem']['points_order'] ?? null;
                }
            }

            if (! $pointsOrder || ! is_array($pointsOrder) || count($pointsOrder) < 1) {
                Log::info('setDefaultCheckpointsAndRefreshDirections: no points_order or empty', [
                    'hiking_route_id' => $hikingRoute->id,
                ]);

                return;
            }

            $pointsOrder = array_map('strval', $pointsOrder);
            $firstPoleId = (int) $pointsOrder[0];
            $lastPoleId = (int) $pointsOrder[count($pointsOrder) - 1];
            $properties['signage']['checkpoint'] = $firstPoleId === $lastPoleId
                ? [$firstPoleId]
                : [$firstPoleId, $lastPoleId];

            if (isset($properties['dem']) && is_array($properties['dem'])) {
                $properties['dem']['points_order'] = $pointsOrder;
            } else {
                $properties['dem'] = ['points_order' => $pointsOrder];
            }

            $hikingRoute->properties = $properties;
            $hikingRoute->saveQuietly();

            $ref = $hikingRoute->osmfeatures_data['properties']['osm_tags']['ref'] ?? '';
            $this->processPointDirections($pointFeaturesMap, $pointsOrder, $properties, $hikingRoute->id, $ref);
            $hikingRoute->properties = $properties;
            $hikingRoute->saveQuietly();
        } catch (Exception $e) {
            Log::warning('setDefaultCheckpointsAndRefreshDirections failed', [
                'hiking_route_id' => $hikingRoute->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mantiene solo le linee con osmfeatures_id valorizzato, preservando gli altri tipi.
     */
    private function filterLineFeaturesWithOsmfeaturesId(array $geojson): array
    {
        $geojson['features'] = array_values(array_filter($geojson['features'] ?? [], function ($feature) {
            $geometryType = strtolower($feature['geometry']['type'] ?? '');
            $isLine = in_array($geometryType, ['linestring', 'multilinestring'], true);

            if (! $isLine) {
                return true;
            }

            return ! empty($feature['properties']['osmfeatures_id']);
        }));

        return $geojson;
    }

    /**
     * Processa le direzioni forward e backward per ogni punto basandosi su points_order e checkpoint
     * e salva i dati nei Pole
     *
     * @param  array  $pointFeaturesMap  mappa id punto -> feature GeoJSON
     * @param  array|null  $pointsOrder  array ordinato di id dei punti lungo la traccia
     * @param  array  $properties  (by-ref) properties dell'HikingRoute che possono essere aggiornate
     */
    private function processPointDirections(array $pointFeaturesMap, ?array $pointsOrder, array &$properties, int $hikingRouteId, string $hikingRouteRef): void
    {
        if (! $pointsOrder || ! is_array($pointsOrder)) {
            Log::warning('points_order not found in geojson');

            return;
        }

        // Estrai checkpoint
        $checkpoints = $properties['signage']['checkpoint'] ?? [];
        if (empty($checkpoints)) {
            Log::info('No checkpoints found, skipping direction calculation');

            return;
        }

        // Converti a stringa per confronti consistenti
        $pointsOrder = array_map('strval', $pointsOrder);
        $checkpoints = array_map('strval', $checkpoints);
        $checkpointSet = array_flip($checkpoints);
        $hikingRouteIdStr = (string) $hikingRouteId;

        // Trova primo e ultimo checkpoint nell'ordine della traccia
        $firstCheckpointId = null;
        $lastCheckpointId = null;
        foreach ($pointsOrder as $pointId) {
            if (isset($checkpointSet[$pointId])) {
                if ($firstCheckpointId === null) {
                    $firstCheckpointId = $pointId;
                }
                $lastCheckpointId = $pointId; // Aggiorna sempre l'ultimo checkpoint trovato
            }
        }

        // La destinazione finale/iniziale è sempre il punto fisico estremo del tracciato.
        // I checkpoint compaiono solo come tappe intermedie (nearest/row[1]).
        $lastId  = end($pointsOrder);
        $firstId = reset($pointsOrder);
        $pointCount = count($pointsOrder);

        // Carica tutti i Poles in una singola query
        $poleIds = array_map('intval', $pointsOrder);
        $poles = Poles::whereIn('id', $poleIds)->get()->keyBy('id');

        // Ciclo unico: calcola direzioni e salva nei Poles
        foreach ($pointsOrder as $i => $pointId) {
            $pointFeature = $pointFeaturesMap[$pointId] ?? null;
            $hikingRouteMatrix = $pointFeature['properties']['dem']['matrix_row'][$hikingRouteIdStr] ?? null;

            if (! $hikingRouteMatrix) {
                continue;
            }

            // Normalizza le chiavi della matrice a stringhe per evitare problemi di tipo
            $normalizedMatrix = [];
            foreach ($hikingRouteMatrix as $key => $value) {
                $normalizedMatrix[(string) $key] = $value;
            }
            $hikingRouteMatrix = $normalizedMatrix;

            // Forward: max 3 righe. Riga 1 = meta più vicina, 2 = intermedia, 3 = meta finale (sempre presente se non siamo alla fine)
            $forward = [];
            for ($j = $i + 1; $j < $pointCount && count($forward) < 2; $j++) {
                if (isset($checkpointSet[$pointsOrder[$j]])) {
                    $forward[] = $pointsOrder[$j];
                }
            }
            // Aggiungi sempre la meta finale come ultima riga (se non già presente)
            if ($lastId !== null && $pointId !== $lastId && ! in_array($lastId, $forward)) {
                $forward[] = $lastId;
            }

            // Backward: max 3 righe. Riga 1 = meta più vicina indietro, 2 = intermedia, 3 = partenza (sempre presente se non siamo all'inizio)
            $backward = [];
            for ($j = $i - 1; $j >= 0 && count($backward) < 2; $j--) {
                if (isset($checkpointSet[$pointsOrder[$j]])) {
                    $backward[] = $pointsOrder[$j];
                }
            }
            // Aggiungi sempre la partenza come ultima riga (se non già presente)
            if ($firstId !== null && $pointId !== $firstId && ! in_array($firstId, $backward)) {
                $backward[] = $firstId;
            }

            // Mappa gli ID agli oggetti dalla matrix, aggiungendo id, name e description del palo target
            $forwardRows = array_values(array_filter(array_map(
                function ($id) use ($hikingRouteMatrix, $pointFeaturesMap) {
                    // Cerca nella matrice normalizzata (chiavi sempre stringhe)
                    $data = $hikingRouteMatrix[(string) $id] ?? null;
                    if (! $data) {
                        return null;
                    }
                    $targetFeature = $pointFeaturesMap[$id] ?? null;

                    $mergedData = array_merge([
                        'id' => (int) $id,
                        'ref' => $targetFeature['properties']['ref'] ?? '',
                        'name' => $targetFeature['properties']['name'] ?? '',
                        'description' => $targetFeature['properties']['description'] ?? '',
                    ], $data);

                    return $this->applyMinimumDisplayValues($mergedData);
                },
                $forward
            )));
            $backwardRows = array_values(array_filter(array_map(
                function ($id) use ($hikingRouteMatrix, $pointFeaturesMap) {
                    // Cerca nella matrice normalizzata (chiavi sempre stringhe)
                    $data = $hikingRouteMatrix[(string) $id] ?? null;
                    if (! $data) {
                        return null;
                    }
                    $targetFeature = $pointFeaturesMap[$id] ?? null;

                    $mergedData = array_merge([
                        'id' => (int) $id,
                        'ref' => $targetFeature['properties']['ref'] ?? '',
                        'name' => $targetFeature['properties']['name'] ?? '',
                        'description' => $targetFeature['properties']['description'] ?? '',
                    ], $data);

                    return $this->applyMinimumDisplayValues($mergedData);
                },
                $backward
            )));

            // Aggiorna il Pole
            $pole = $poles[(int) $pointId] ?? null;
            if (! $pole) {
                continue;
            }

            $poleProperties = $pole->properties ?? [];

            // Inizializza la struttura signage se non esiste
            if (! isset($poleProperties['signage']) || ! is_array($poleProperties['signage'])) {
                $poleProperties['signage'] = [];
            }

            // Inizializza arrow_order se non esiste
            if (! isset($poleProperties['signage']['arrow_order']) || ! is_array($poleProperties['signage']['arrow_order'])) {
                $poleProperties['signage']['arrow_order'] = [];
            }

            // Recupera eventuali frecce già salvate per questo hiking route
            $existingRouteSignage = $poleProperties['signage'][$hikingRouteIdStr] ?? null;
            $existingArrows = [];
            if (is_array($existingRouteSignage) && isset($existingRouteSignage['arrows']) && is_array($existingRouteSignage['arrows'])) {
                $existingArrows = $existingRouteSignage['arrows'];
            }

            // midpoints_data: time/distance per tutti i checkpoint del route da questo palo.
            // Stabile (dipende dalla geometria), usato a runtime per popolare available_midpoints.
            $allCheckpointMidpointsData = [];
            foreach ($checkpoints as $checkpointId) {
                $matrixEntry = $hikingRouteMatrix[(string) $checkpointId] ?? null;
                if ($matrixEntry) {
                    $allCheckpointMidpointsData[(string) $checkpointId] = $matrixEntry;
                }
            }

            $arrows = [];

            if (!empty($forwardRows)) {
                $direction = $existingArrows[0]['direction'] ?? 'forward';

                $selectedFwMidId = isset($existingArrows[0]['selected_midpoint_id'])
                    ? (int) $existingArrows[0]['selected_midpoint_id']
                    : null;

                // Applica override a rows[1] se il midpoint selezionato ha dati validi
                if ($selectedFwMidId && isset($forwardRows[1]) && isset($allCheckpointMidpointsData[(string) $selectedFwMidId])) {
                    $midFeature = $pointFeaturesMap[(string) $selectedFwMidId] ?? null;
                    $forwardRows[1] = $this->applyMinimumDisplayValues(array_merge([
                        'id'          => $selectedFwMidId,
                        'name'        => $midFeature['properties']['name'] ?? '',
                        'ref'         => $midFeature['properties']['ref'] ?? '',
                        'description' => $midFeature['properties']['description'] ?? '',
                    ], $allCheckpointMidpointsData[(string) $selectedFwMidId]));
                }

                $arrowData = [
                    'direction'      => $direction,
                    'rows'           => $forwardRows,
                    'midpoints_data' => $allCheckpointMidpointsData,
                ];
                if ($selectedFwMidId) {
                    $arrowData['selected_midpoint_id'] = $selectedFwMidId;
                }
                $arrows[] = $arrowData;
            }

            if (!empty($backwardRows)) {
                $backwardIndex = !empty($forwardRows) ? 1 : 0;
                $direction = $existingArrows[$backwardIndex]['direction'] ?? 'backward';

                $selectedBwMidId = isset($existingArrows[$backwardIndex]['selected_midpoint_id'])
                    ? (int) $existingArrows[$backwardIndex]['selected_midpoint_id']
                    : null;

                if ($selectedBwMidId && isset($backwardRows[1]) && isset($allCheckpointMidpointsData[(string) $selectedBwMidId])) {
                    $midFeature = $pointFeaturesMap[(string) $selectedBwMidId] ?? null;
                    $backwardRows[1] = $this->applyMinimumDisplayValues(array_merge([
                        'id'          => $selectedBwMidId,
                        'name'        => $midFeature['properties']['name'] ?? '',
                        'ref'         => $midFeature['properties']['ref'] ?? '',
                        'description' => $midFeature['properties']['description'] ?? '',
                    ], $allCheckpointMidpointsData[(string) $selectedBwMidId]));
                }

                $arrowData = [
                    'direction'      => $direction,
                    'rows'           => $backwardRows,
                    'midpoints_data' => $allCheckpointMidpointsData,
                ];
                if ($selectedBwMidId) {
                    $arrowData['selected_midpoint_id'] = $selectedBwMidId;
                }
                $arrows[] = $arrowData;
            }

            // Aggiorna la struttura per questo hiking route
            $poleProperties['signage'][$hikingRouteIdStr] = [
                'ref' => $hikingRouteRef,
                'arrows' => $arrows,
            ];

            // Aggiorna arrow_order preservando l'ordine esistente per questa hiking route
            $existingOrder = $poleProperties['signage']['arrow_order'] ?? [];
            $poleProperties['signage']['arrow_order'] = $this->mergeArrowOrderForRoute(
                $existingOrder,
                $hikingRouteIdStr,
                $arrows
            );

            $pole->properties = $poleProperties;
            $pole->saveQuietly();
        }

        // Salva checkpoint_order nella HikingRoute per uso runtime in resolveAttribute
        $orderedCheckpoints = array_values(array_filter(
            array_map('intval', $pointsOrder),
            fn($id) => isset($checkpointSet[(string) $id])
        ));

        $hikingRoute = HikingRoute::find($hikingRouteId);
        if ($hikingRoute) {
            $hrProperties = $hikingRoute->properties ?? [];
            if (!isset($hrProperties['signage'])) {
                $hrProperties['signage'] = [];
            }
            $hrProperties['signage']['checkpoint_order'] = $orderedCheckpoints;
            $hikingRoute->properties = $hrProperties;
            $hikingRoute->saveQuietly();
        }
    }

    /**
     * Effettua il merge di arrow_order per una singola hiking route,
     * preservando l'ordine esistente dove possibile.
     *
     * @param  array  $existingOrder  array completo arrow_order del palo
     * @param  string  $hikingRouteIdStr  id della hiking route come stringa
     * @param  array  $arrows  array di arrows correnti per la route
     * @return array nuovo array arrow_order completo
     */
    private function mergeArrowOrderForRoute(array $existingOrder, string $hikingRouteIdStr, array $arrows): array
    {
        // Calcola l'insieme delle chiavi consentite per questa route in base alle frecce presenti
        $allowedKeys = [];
        foreach (array_keys($arrows) as $idx) {
            $allowedKeys[] = $hikingRouteIdStr . '-' . $idx;
        }

        // Se non ci sono frecce, rimuovi semplicemente tutte le chiavi di questa route
        if (empty($allowedKeys)) {
            return array_values(array_filter(
                $existingOrder,
                function ($key) use ($hikingRouteIdStr) {
                    return ! str_starts_with($key, $hikingRouteIdStr . '-');
                }
            ));
        }

        // 1) Mantieni l'ordine esistente per le chiavi valide di questa route
        $updatedOrder = [];
        foreach ($existingOrder as $key) {
            // Chiavi di altre route rimangono invariate
            if (! str_starts_with($key, $hikingRouteIdStr . '-')) {
                $updatedOrder[] = $key;
                continue;
            }

            // Per questa route: tieni solo le chiavi ancora valide
            if (in_array($key, $allowedKeys, true)) {
                $updatedOrder[] = $key;
            }
            // Se non è più valida (es. freccia rimossa), viene scartata
        }

        // 2) Aggiungi eventuali nuove chiavi mancanti per questa route, in ordine di indice
        foreach ($allowedKeys as $key) {
            if (! in_array($key, $updatedOrder, true)) {
                $updatedOrder[] = $key;
            }
        }

        return array_values($updatedOrder);
    }

    /**
     * Aggiorna la direzione di una freccia nella segnaletica di un palo
     */
    public function updateArrowDirection(Request $request, int $poleId): JsonResponse
    {
        $pole = Poles::find($poleId);

        if (! $pole) {
            // #endregion
            return response()->json(['error' => 'Pole not found'], 404);
        }

        $routeId = $request->input('routeId');
        $arrowIndex = $request->input('arrowIndex');
        $newDirection = $request->input('newDirection');

        if ($routeId === null || $arrowIndex === null || $newDirection === null) {
            // #endregion
            return response()->json(['error' => 'routeId, arrowIndex and newDirection are required'], 400);
        }

        $poleProperties = $pole->properties ?? [];

        // Inizializza la struttura signage se non esiste
        if (! isset($poleProperties['signage']) || ! is_array($poleProperties['signage'])) {
            $poleProperties['signage'] = [];
        }

        // Verifica che la route esista nella struttura signage
        if (! isset($poleProperties['signage'][$routeId]) || ! is_array($poleProperties['signage'][$routeId])) {
            // #endregion
            return response()->json(['error' => 'Route not found in signage structure'], 404);
        }

        $routeSignage = &$poleProperties['signage'][$routeId];

        // Verifica che arrows esista e che arrowIndex sia valido
        if (! isset($routeSignage['arrows']) || ! is_array($routeSignage['arrows'])) {
            // #endregion
            return response()->json(['error' => 'Arrows not found for this route'], 404);
        }

        if (! isset($routeSignage['arrows'][$arrowIndex])) {
            // #endregion
            return response()->json(['error' => 'Invalid arrow index'], 400);
        }

        // Aggiorna la direzione
        $routeSignage['arrows'][$arrowIndex]['direction'] = $newDirection;

        // #endregion

        $pole->properties = $poleProperties;
        $pole->saveQuietly();

        // Prepara i dati signage per la risposta (formato con wrapper "signage")
        $signageData = [
            'signage' => $poleProperties['signage'],
        ];

        // #endregion

        return response()->json([
            'success' => true,
            'signageData' => $signageData,
        ]);
    }

    /**
     * Aggiorna l'ordine delle frecce per un palo
     */
    public function updateArrowOrder(Request $request, int $poleId): JsonResponse
    {
        $pole = Poles::find($poleId);

        if (! $pole) {
            return response()->json(['error' => 'Pole not found'], 404);
        }

        $routeId = $request->input('routeId');
        $arrowOrder = $request->input('arrowOrder');

        if ($routeId === null || ! is_array($arrowOrder)) {
            return response()->json(['error' => 'routeId and arrowOrder are required'], 400);
        }

        $poleProperties = $pole->properties ?? [];

        // Inizializza la struttura signage se non esiste
        if (! isset($poleProperties['signage']) || ! is_array($poleProperties['signage'])) {
            $poleProperties['signage'] = [];
        }

        // Verifica che la route esista nella struttura signage
        if (! isset($poleProperties['signage'][$routeId]) || ! is_array($poleProperties['signage'][$routeId])) {
            return response()->json(['error' => 'Route not found in signage structure'], 404);
        }

        // Aggiorna l'array arrow_order complessivo
        $poleProperties['signage']['arrow_order'] = array_values($arrowOrder);

        $pole->properties = $poleProperties;
        $pole->saveQuietly();

        // Prepara i dati signage per la risposta (formato con wrapper "signage")
        $signageData = [
            'signage' => $poleProperties['signage'],
        ];

        return response()->json([
            'success' => true,
            'signageData' => $signageData,
        ]);
    }

    /**
     * Aggiorna la meta intermedia (rows[1]) di una freccia.
     * Valida selected_pole_id a runtime contro checkpoint_order della HikingRoute.
     * Persiste selected_midpoint_id nell'arrow.
     *
     * Body: { hiking_route_id: int, arrow_index: int, selected_pole_id: int }
     */
    public function updateArrowMidpoint(Request $request, int $poleId): JsonResponse
    {
        $validated = $request->validate([
            'hiking_route_id'  => 'required|integer',
            'arrow_index'      => 'required|integer|min:0',
            'selected_pole_id' => 'required|integer',
        ]);

        $pole             = Poles::findOrFail($poleId);
        $poleProperties   = $pole->properties ?? [];
        $hikingRouteIdStr = (string) $validated['hiking_route_id'];
        $arrowIndex       = (int) $validated['arrow_index'];
        $selectedPoleId   = (int) $validated['selected_pole_id'];

        // Valida prima la HikingRoute (errore semanticamente prioritario)
        $hikingRoute = HikingRoute::find((int) $hikingRouteIdStr);
        if (!$hikingRoute) {
            return response()->json(['error' => 'HikingRoute not found'], 404);
        }

        if (!isset($poleProperties['signage'][$hikingRouteIdStr]['arrows'][$arrowIndex])) {
            return response()->json(['error' => 'Arrow not found'], 404);
        }

        $arrow = &$poleProperties['signage'][$hikingRouteIdStr]['arrows'][$arrowIndex];
        $rows  = $arrow['rows'] ?? [];

        if (count($rows) < 3) {
            return response()->json(['error' => 'Arrow has no midpoint slot'], 422);
        }

        $hrSignage         = $hikingRoute->properties['signage'] ?? [];
        $checkpointOrder   = array_map('intval', $hrSignage['checkpoint_order'] ?? []);
        $activeCheckpoints = array_map('intval', $hrSignage['checkpoint'] ?? []);
        $activeSet         = array_flip($activeCheckpoints);

        $nearestId   = (int) ($rows[0]['id'] ?? 0);
        $finalId     = (int) ($rows[count($rows) - 1]['id'] ?? 0);
        $nearestPos  = array_search($nearestId, $checkpointOrder);
        $finalPos    = array_search($finalId, $checkpointOrder);
        $selectedPos = array_search($selectedPoleId, $checkpointOrder);

        $start = min($nearestPos, $finalPos);
        $end   = max($nearestPos, $finalPos);

        $isValid = $selectedPos !== false
            && $selectedPos > $start
            && $selectedPos < $end
            && isset($activeSet[$selectedPoleId])
            && $selectedPoleId !== $nearestId
            && $selectedPoleId !== $finalId;

        if (!$isValid) {
            return response()->json(['error' => 'Selected pole is not a valid intermediate checkpoint'], 422);
        }

        // Recupera time/distance da midpoints_data (già calcolati in processPointDirections)
        $midData      = $arrow['midpoints_data'][(string) $selectedPoleId] ?? [];
        $selectedPole = Poles::find($selectedPoleId);

        $arrow['rows'][1] = array_merge([
            'id'          => $selectedPoleId,
            'name'        => $selectedPole?->name ?? '',
            'ref'         => $selectedPole?->ref ?? '',
            'description' => '',
        ], $midData);

        $arrow['selected_midpoint_id'] = $selectedPoleId;

        $pole->properties = $poleProperties;
        $pole->saveQuietly();

        return response()->json(['success' => true, 'arrow' => $arrow]);
    }

    /**
     * Restituisce available_midpoints per ogni arrow del palo, calcolati a runtime
     * usando checkpoint_order della HikingRoute.
     *
     * GET /pole/{poleId}/available-midpoints
     */
    public function getAvailableMidpoints(int $poleId): JsonResponse
    {
        $pole           = Poles::findOrFail($poleId);
        $poleProperties = $pole->properties ?? [];
        $signage        = $poleProperties['signage'] ?? [];

        $result = [];

        foreach ($signage as $hikingRouteIdStr => $routeData) {
            if ($hikingRouteIdStr === 'arrow_order' || ! is_array($routeData) || ! isset($routeData['arrows'])) {
                continue;
            }

            $hikingRoute = HikingRoute::find((int) $hikingRouteIdStr);
            if (! $hikingRoute) {
                continue;
            }

            $hrSignage         = $hikingRoute->properties['signage'] ?? [];
            $checkpointOrder   = array_map('intval', $hrSignage['checkpoint_order'] ?? []);
            $activeCheckpoints = array_map('intval', $hrSignage['checkpoint'] ?? []);
            $activeSet         = array_flip($activeCheckpoints);

            if (empty($checkpointOrder)) {
                continue;
            }

            $poleNames = Poles::whereIn('id', $checkpointOrder)
                ->get(['id', 'name', 'ref'])
                ->keyBy('id');

            foreach ($routeData['arrows'] as $arrowIdx => $arrow) {
                $key = "{$hikingRouteIdStr}-{$arrowIdx}";

                if (! isset($arrow['rows']) || count($arrow['rows']) < 3) {
                    $result[$key] = [];
                    continue;
                }

                $nearestId  = (int) ($arrow['rows'][0]['id'] ?? 0);
                $finalId    = (int) ($arrow['rows'][count($arrow['rows']) - 1]['id'] ?? 0);
                $nearestPos = array_search($nearestId, $checkpointOrder);
                $finalPos   = array_search($finalId, $checkpointOrder);

                if ($nearestPos === false || $finalPos === false) {
                    $result[$key] = [];
                    continue;
                }

                $start     = min($nearestPos, $finalPos);
                $end       = max($nearestPos, $finalPos);
                $midpoints = [];

                for ($k = $start + 1; $k < $end; $k++) {
                    $midId = $checkpointOrder[$k];
                    if ($midId === $nearestId || $midId === $finalId) {
                        continue;
                    }
                    if (! isset($activeSet[$midId])) {
                        continue;
                    }
                    $midData     = $arrow['midpoints_data'][(string) $midId] ?? [];
                    $p           = $poleNames->get($midId);
                    $midpoints[] = array_merge([
                        'id'          => $midId,
                        'name'        => $p?->name ?? '',
                        'ref'         => $p?->ref ?? '',
                        'description' => '',
                    ], $midData);
                }

                $result[$key] = $midpoints;
            }
        }

        return response()->json(['available_midpoints' => $result]);
    }

    /**
     * Applica i valori minimi di visualizzazione per tempo (5 min) e distanza (0,1 km).
     *
     * @param  array  $mergedData  Riga direzione con id, ref, name, description e dati matrix (distance, time_hiking, ...)
     * @return array Stesso array con time_hiking arrotondato e distance >= 100 m
     */
    private function applyMinimumDisplayValues(array $mergedData): array
    {
        if (isset($mergedData['time_hiking'])) {
            $mergedData['time_hiking'] = $this->roundTravelTime($mergedData['time_hiking']);
        }
        if (isset($mergedData['distance']) && (int) $mergedData['distance'] < 100) {
            $mergedData['distance'] = 100;
        }

        return $mergedData;
    }

    /**
     * Arrotonda i tempi di percorrenza secondo le regole CAI
     *
     * Regole di arrotondamento:
     * - Prima ora (0-60 min): mantiene tutti i valori, ma 55-60 > 60
     * - Seconda ora (61-120 min): arrotonda 5, 25, 35, 55 ai 10 minuti successivi
     * - Terza/Quarta ora (121-240 min): arrotondamenti più ampi
     * - Successive (>240 min): arrotondamenti ancora più ampi
     *
     * @param  int|null  $minutes  Tempo in minuti
     * @return int|null Tempo arrotondato in minuti
     */
    private function roundTravelTime(?int $minutes): ?int
    {


        // --- Prima ora: 0‑60 minuti ---
        // Non ha senso mostrare tempi inferiori a 5', li portiamo a 5'.
        if ($minutes === null || $minutes <= 5) {
            return 5;
        }
        if ($minutes <= 10) {
            return 10;
        }
        if ($minutes <= 15) {
            return 15;
        }
        if ($minutes <= 20) {
            return 20;
        }
        if ($minutes <= 25) {
            return 25;
        }
        if ($minutes <= 30) {
            return 30;
        }
        if ($minutes <= 35) {
            return 35;
        }
        if ($minutes <= 40) {
            return 40;
        }
        if ($minutes <= 45) {
            return 45;
        }
        if ($minutes <= 50) {
            return 50;
        }
        if ($minutes <= 60) {
            return 60;
        }

        // --- Seconda ora: 61‑120 minuti ---
        if ($minutes <= 70) {   // 1:05‑1:10 -> 1:10
            return 70;
        }
        if ($minutes <= 75) {   // 1:15
            return 75;
        }
        if ($minutes <= 80) {   // 1:20
            return 80;
        }
        if ($minutes <= 90) {   // 1:25‑1:30
            return 90;
        }
        if ($minutes <= 100) {  // 1:35‑1:40
            return 100;
        }
        if ($minutes <= 105) {  // 1:45
            return 105;
        }
        if ($minutes <= 110) {  // 1:50
            return 110;
        }
        if ($minutes <= 120) {  // 1:55‑2:00
            return 120;
        }

        // --- Terza / Quarta ora: 121‑240 minuti ---
        if ($minutes <= 130) {  // 2:05‑2:10
            return 130;
        }
        if ($minutes <= 135) {  // 2:15
            return 135;
        }
        if ($minutes <= 140) {  // 2:20
            return 140;
        }
        if ($minutes <= 150) {  // 2:25‑2:30
            return 150;
        }
        if ($minutes <= 160) {  // 2:35‑2:40
            return 160;
        }
        if ($minutes <= 180) {  // 2:45‑3:05
            return 180;
        }
        if ($minutes <= 210) {  // 3:10‑3:30
            return 210;
        }
        if ($minutes <= 240) {  // 3:35‑4:00
            return 240;
        }

        // --- Ore successive: 241‑420 minuti (fino a 7 ore) ---
        if ($minutes <= 270) {  // 4:05‑4:30
            return 270;
        }
        if ($minutes <= 300) {  // 4:35‑5:00
            return 300;
        }
        if ($minutes <= 330) {  // 5:05‑5:30
            return 330;
        }
        if ($minutes <= 360) {  // 5:35‑6:00
            return 360;
        }
        if ($minutes <= 390) {  // 6:05‑6:30
            return 390;
        }
        if ($minutes <= 420) {  // 6:35‑7:00
            return 420;
        }

        // --- Dalle 7 ore in poi ---
        // Regola uniforme con tolleranza di 4 minuti:
        //  - H:00‑H:04  -> H:00
        //  - H:05‑H:59  -> (H+1):00
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($mins <= 4) {
            return $hours * 60;
        }

        return ($hours + 1) * 60;
    }
}
