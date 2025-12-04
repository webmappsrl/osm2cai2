<?php

namespace Wm\SignageMap\Http\Controllers;

use App\Models\HikingRoute;
use App\Models\Poles;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Wm\WmPackage\Http\Clients\DemClient;

class SignageMapController
{
    /**
     * Aggiorna le properties dell'hikingRoute aggiungendo/rimuovendo checkpoint
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

        // Ottieni l'ID del palo e l'azione (add/remove)
        $poleId = $request->input('poleId');
        $add = $request->boolean('add');

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

        // Salva le properties aggiornate
        $hikingRoute->properties = $properties;
        $hikingRoute->saveQuietly();

        // Ottieni il GeoJSON e chiama il DEM per arricchire con point matrix
        $geojson = null;
        try {
            $geojson = $hikingRoute->getFeatureCollectionMap();
            $demClient = new DemClient;
            $geojson = $demClient->getPointMatrix($geojson);

            // Estrai points_order e checkpoint dal GeoJSON e calcola le direzioni
            $this->processPointDirections($geojson, $properties, $id);
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
     * Processa le direzioni forward e backward per ogni punto basandosi su points_order e checkpoint
     * e salva i dati nei Pole
     */
    private function processPointDirections(array $geojson, array &$properties, int $hikingRouteId): void
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

            // Calcola forward: prossimi 2 checkpoint + ultimo punto
            $forward = [];
            for ($j = $i + 1; $j < $pointCount && count($forward) < 2; $j++) {
                if (isset($checkpointSet[$pointsOrder[$j]])) {
                    $forward[] = $pointsOrder[$j];
                }
            }
            if ($pointId !== $lastId) {
                $forward[] = $lastId;
            }

            // Calcola backward: precedenti 2 checkpoint + primo punto
            $backward = [];
            for ($j = $i - 1; $j >= 0 && count($backward) < 2; $j--) {
                if (isset($checkpointSet[$pointsOrder[$j]])) {
                    $backward[] = $pointsOrder[$j];
                }
            }
            if ($pointId !== $firstId) {
                $backward[] = $firstId;
            }

            // Mappa gli ID agli oggetti dalla matrix, aggiungendo id e name del palo target
            $forwardObjects = array_values(array_filter(array_map(
                function ($id) use ($hikingRouteMatrix, $pointFeaturesMap) {
                    $data = $hikingRouteMatrix[$id] ?? null;
                    if (! $data) {
                        return null;
                    }
                    $targetFeature = $pointFeaturesMap[$id] ?? null;

                    return array_merge([
                        'id' => (int) $id,
                        'name' => $targetFeature['properties']['name'] ?? '',
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
                        'name' => $targetFeature['properties']['name'] ?? null,
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
            $poleProperties['signage'][$hikingRouteIdStr] = [
                'forward' => $forwardObjects,
                'backward' => $backwardObjects,
            ];

            $pole->properties = $poleProperties;
            $pole->saveQuietly();
        }
    }
}
