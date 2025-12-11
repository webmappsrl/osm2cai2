<?php

namespace Osm2cai\SignageMap\Http\Controllers;

use App\Http\Clients\NominatimClient;
use App\Models\HikingRoute;
use App\Models\Poles;
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
     * e salva il placeName nelle properties del Pole
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

        // Ottieni l'ID del palo, l'azione (add/remove), placeName e placeDescription
        $poleId = $request->input('poleId');
        $add = $request->boolean('add');
        $placeName = $request->input('placeName');
        $placeDescription = $request->input('placeDescription');

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

        // Salva placeName e placeDescription nelle properties del Pole se forniti
        if ($add && ($placeName !== null || $placeDescription !== null)) {
            $pole = Poles::find($poleId);
            if ($pole) {
                $poleProperties = $pole->properties ?? [];
                if ($placeName !== null) {
                    $poleProperties['placeName'] = $placeName;
                }
                if ($placeDescription !== null) {
                    $poleProperties['placeDescription'] = $placeDescription;
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
            $geojson = $hikingRoute->getFeatureCollectionMap();
            $geojson = $this->filterLineFeaturesWithOsmfeaturesId($geojson);
            $demClient = new DemClient;
            $geojson = $demClient->getPointMatrix($geojson);
            $ref = $hikingRoute->osmfeatures_data['properties']['osm_tags']['ref'] ?? '';
            // Estrai points_order e checkpoint dal GeoJSON e calcola le direzioni
            $this->processPointDirections($geojson, $properties, $id, $ref);
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

        return response()->json([
            'success' => true,
            'properties' => $hikingRoute->properties,
        ]);
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
     */
    private function processPointDirections(array $geojson, array &$properties, int $hikingRouteId, string $hikingRouteRef): void
    {
        // Estrai features in un singolo ciclo: Point features map e MultiLineString
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

        // Prepara riferimenti per direzioni
        $lastId = end($pointsOrder);
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

            // Calcola forward: prossimi 2 checkpoint + ultimo punto (se non già presente)
            $forward = [];
            for ($j = $i + 1; $j < $pointCount && count($forward) < 2; $j++) {
                if (isset($checkpointSet[$pointsOrder[$j]])) {
                    $forward[] = $pointsOrder[$j];
                }
            }
            // Aggiungi l'ultimo punto solo se non è il punto corrente e non è già presente
            if ($pointId !== $lastId && ! in_array($lastId, $forward)) {
                $forward[] = $lastId;
            }

            // Calcola backward: precedenti 2 checkpoint + primo punto (se non già presente)
            $backward = [];
            for ($j = $i - 1; $j >= 0 && count($backward) < 2; $j--) {
                if (isset($checkpointSet[$pointsOrder[$j]])) {
                    $backward[] = $pointsOrder[$j];
                }
            }
            // Aggiungi il primo punto solo se non è il punto corrente e non è già presente
            if ($pointId !== $firstId && ! in_array($firstId, $backward)) {
                $backward[] = $firstId;
            }

            // Mappa gli ID agli oggetti dalla matrix, aggiungendo id, placeName e placeDescription del palo target
            $forwardObjects = array_values(array_filter(array_map(
                function ($id) use ($hikingRouteMatrix, $pointFeaturesMap) {
                    $data = $hikingRouteMatrix[$id] ?? null;
                    if (! $data) {
                        return null;
                    }
                    $targetFeature = $pointFeaturesMap[$id] ?? null;

                    return array_merge([
                        'id' => (int) $id,
                        'placeName' => $targetFeature['properties']['placeName'] ?? '',
                        'placeDescription' => $targetFeature['properties']['placeDescription'] ?? '',
                    ], $data);
                },
                $forward
            )));
            $backwardObjects = array_values(array_filter(array_map(
                function ($id) use ($hikingRouteMatrix, $pointFeaturesMap) {
                    $data = $hikingRouteMatrix[$id] ?? null;
                    if (! $data) {
                        return null;
                    }
                    $targetFeature = $pointFeaturesMap[$id] ?? null;

                    return array_merge([
                        'id' => (int) $id,
                        'placeName' => $targetFeature['properties']['placeName'] ?? '',
                        'placeDescription' => $targetFeature['properties']['placeDescription'] ?? '',
                    ], $data);
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

            // Crea la struttura arrows con direction e rows
            $arrows = [];
            if (! empty($forwardObjects)) {
                $arrows[] = [
                    'direction' => 'forward',
                    'rows' => $forwardObjects,
                ];
            }
            if (! empty($backwardObjects)) {
                $arrows[] = [
                    'direction' => 'backward',
                    'rows' => $backwardObjects,
                ];
            }

            // Aggiorna la struttura per questo hiking route
            $poleProperties['signage'][$hikingRouteIdStr] = [
                'ref' => $hikingRouteRef,
                'arrows' => $arrows,
            ];

            // Aggiorna arrow_order: aggiungi le chiavi per questo hiking route se non già presenti
            $forwardKey = $hikingRouteIdStr . '-0';
            $backwardKey = $hikingRouteIdStr . '-1';

            // Rimuovi eventuali chiavi esistenti per questo hiking route
            $poleProperties['signage']['arrow_order'] = array_values(array_filter(
                $poleProperties['signage']['arrow_order'],
                function ($key) use ($hikingRouteIdStr) {
                    return ! str_starts_with($key, $hikingRouteIdStr . '-');
                }
            ));

            // Aggiungi le nuove chiavi nell'ordine corretto (forward prima, backward dopo)
            if (! empty($forwardObjects)) {
                $poleProperties['signage']['arrow_order'][] = $forwardKey;
            }
            if (! empty($backwardObjects)) {
                $poleProperties['signage']['arrow_order'][] = $backwardKey;
            }

            $pole->properties = $poleProperties;
            $pole->saveQuietly();
        }
    }

    /**
     * Aggiorna la direzione di una freccia nella segnaletica di un palo
     */
    public function updateArrowDirection(Request $request, int $poleId): JsonResponse
    {
        // #region agent log
        $logPath = base_path('.cursor/debug.log');
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        @file_put_contents($logPath, json_encode(['location' => 'SignageMapController.php:updateArrowDirection', 'message' => 'Metodo chiamato', 'data' => ['poleId' => $poleId, 'requestData' => $request->all()], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'E']) . "\n", FILE_APPEND);
        // #endregion

        $pole = Poles::find($poleId);

        if (! $pole) {
            // #region agent log
            @file_put_contents(base_path('.cursor/debug.log'), json_encode(['location' => 'SignageMapController.php:updateArrowDirection', 'message' => 'Palo non trovato', 'data' => ['poleId' => $poleId], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'E']) . "\n", FILE_APPEND);
            // #endregion
            return response()->json(['error' => 'Pole not found'], 404);
        }

        $routeId = $request->input('routeId');
        $arrowIndex = $request->input('arrowIndex');
        $newDirection = $request->input('newDirection');

        if ($routeId === null || $arrowIndex === null || $newDirection === null) {
            // #region agent log
            @file_put_contents(base_path('.cursor/debug.log'), json_encode(['location' => 'SignageMapController.php:updateArrowDirection', 'message' => 'Parametri mancanti', 'data' => ['routeId' => $routeId, 'arrowIndex' => $arrowIndex, 'newDirection' => $newDirection], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'E']) . "\n", FILE_APPEND);
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
            // #region agent log
            $availableRoutes = array_keys($poleProperties['signage'] ?? []);
            // #endregion
            return response()->json(['error' => 'Route not found in signage structure'], 404);
        }

        $routeSignage = &$poleProperties['signage'][$routeId];

        // Verifica che arrows esista e che arrowIndex sia valido
        if (! isset($routeSignage['arrows']) || ! is_array($routeSignage['arrows'])) {
            // #region agent log
            @file_put_contents(base_path('.cursor/debug.log'), json_encode(['location' => 'SignageMapController.php:updateArrowDirection', 'message' => 'Arrows non trovato nella route', 'data' => ['routeId' => $routeId], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'E']) . "\n", FILE_APPEND);
            // #endregion
            return response()->json(['error' => 'Arrows not found for this route'], 404);
        }

        if (! isset($routeSignage['arrows'][$arrowIndex])) {
            // #region agent log
            @file_put_contents(base_path('.cursor/debug.log'), json_encode(['location' => 'SignageMapController.php:updateArrowDirection', 'message' => 'Arrow index non valido', 'data' => ['routeId' => $routeId, 'arrowIndex' => $arrowIndex, 'arrowsCount' => count($routeSignage['arrows'])], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'E']) . "\n", FILE_APPEND);
            // #endregion
            return response()->json(['error' => 'Invalid arrow index'], 400);
        }

        // Aggiorna la direzione
        $routeSignage['arrows'][$arrowIndex]['direction'] = $newDirection;

        // #region agent log
        @file_put_contents(base_path('.cursor/debug.log'), json_encode(['location' => 'SignageMapController.php:updateArrowDirection', 'message' => 'Direzione aggiornata, salvataggio palo', 'data' => ['routeId' => $routeId, 'arrowIndex' => $arrowIndex, 'newDirection' => $newDirection], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'E']) . "\n", FILE_APPEND);
        // #endregion

        $pole->properties = $poleProperties;
        $pole->saveQuietly();

        // Prepara i dati signage per la risposta (formato con wrapper "signage")
        $signageData = [
            'signage' => $poleProperties['signage']
        ];

        // #region agent log
        @file_put_contents(base_path('.cursor/debug.log'), json_encode(['location' => 'SignageMapController.php:updateArrowDirection', 'message' => 'Salvataggio completato', 'data' => ['success' => true], 'timestamp' => time() * 1000, 'sessionId' => 'debug-session', 'runId' => 'run1', 'hypothesisId' => 'E']) . "\n", FILE_APPEND);
        // #endregion

        return response()->json([
            'success' => true,
            'signageData' => $signageData
        ]);
    }
}
