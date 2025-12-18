<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait OsmfeaturesGeometryUpdateTrait
{
    protected static function updateGeometry($model, $osmfeaturesData, $osmfeaturesId)
    {
        $updateData = [];

        if (! isset($osmfeaturesData['geometry'])) {
            Log::channel('wm-osmfeatures')->info('No geometry found for ' . class_basename($model) . ' ' . $osmfeaturesId);
            return $updateData;
        }
        if (isset($model->osm2cai_status) && $model->osm2cai_status > 3) {
            Log::channel('wm-osmfeatures')->info('Osm2cai status is greater than 3, skipping geometry update for ' . class_basename($model) . ' ' . $osmfeaturesId);
            return $updateData;
        }
        // Format the new geometry - add Z dimension (0) to match database schema
        $newGeometry = DB::selectOne(
            'SELECT ST_AsText(ST_Force3DZ(ST_GeomFromGeoJSON(?), 0)) as geometry',
            [json_encode($osmfeaturesData['geometry'])]
        )->geometry;

        // if model geometry is null, set the new geometry
        if ($model->geometry === null) {
            $updateData['geometry'] = DB::raw("ST_GeomFromText('$newGeometry')");
            Log::channel('wm-osmfeatures')->info('Geometry updated for ' . class_basename($model) . ' ' . $osmfeaturesId);

            return $updateData;
        }


        // Confronta usando lo stesso metodo di getGeometrySyncAttribute per coerenza
        // Converte entrambe le geometrie in GeoJSON e normalizza rimuovendo Z
        $dbGeojson = DB::selectOne(
            'SELECT ST_AsGeoJSON(?) as geojson',
            [$model->geometry]
        )->geojson;
        $dbGeom = json_decode($dbGeojson, true);
        $osmfeaturesGeom = $osmfeaturesData['geometry'] ?? null;

        if (!$dbGeom || !$osmfeaturesGeom) {
            $geometryChanged = true; // Se manca una delle due, considera cambiata
            Log::debug('OsmfeaturesGeometryUpdateTrait: Geometria mancante', [
                'model_id' => $model->id,
                'osmfeatures_id' => $osmfeaturesId,
                'has_db_geom' => !empty($dbGeom),
                'has_osmfeatures_geom' => !empty($osmfeaturesGeom),
            ]);
        } else {
            // Normalizza entrambe le geometrie rimuovendo la dimensione Z
            $normalizedDbGeom = OsmfeaturesGeometryUpdateTrait::normalizeGeometry($dbGeom);
            $normalizedOsmfeaturesGeom = OsmfeaturesGeometryUpdateTrait::normalizeGeometry($osmfeaturesGeom);

            // Confronta usando JSON per un confronto robusto di array annidati complessi
            // (stesso metodo di getGeometrySyncAttribute per coerenza)
            if ($normalizedDbGeom === null && $normalizedOsmfeaturesGeom === null) {
                $geometryChanged = false;
            } elseif ($normalizedDbGeom === null || $normalizedOsmfeaturesGeom === null) {
                $geometryChanged = true;
            } else {
                // Confronta le geometrie normalizzate usando JSON per un confronto deterministico
                $geometryChanged = json_encode($normalizedDbGeom, JSON_UNESCAPED_SLASHES) !== json_encode($normalizedOsmfeaturesGeom, JSON_UNESCAPED_SLASHES);
            }

            Log::debug('OsmfeaturesGeometryUpdateTrait: Confronto geometrie normalizzate', [
                'model_id' => $model->id,
                'osmfeatures_id' => $osmfeaturesId,
                'geometry_changed' => $geometryChanged,
                'db_geom_type' => $dbGeom['type'] ?? null,
                'osmfeatures_geom_type' => $osmfeaturesGeom['type'] ?? null,
            ]);
        }

        // Update the geometry only if it has changed
        if ($geometryChanged) {
            $updateData['geometry'] = DB::raw("ST_GeomFromText('$newGeometry')");
            Log::channel('wm-osmfeatures')->info('Geometry updated for ' . class_basename($model) . ' ' . $osmfeaturesId);
        } else {
            Log::channel('wm-osmfeatures')->info('No geometry change for ' . class_basename($model) . ' ' . $osmfeaturesId);
        }

        return $updateData;
    }

    /**
     * Normalizza una geometria rimuovendo la dimensione Z (se presente)
     * per permettere il confronto tra geometrie con e senza Z
     */
    public static function normalizeGeometry($geometry): ?array
    {
        if (! is_array($geometry)) {
            return $geometry;
        }

        if (isset($geometry['coordinates'])) {
            $geometry['coordinates'] = OsmfeaturesGeometryUpdateTrait::removeZDimension($geometry['coordinates']);
        }

        return $geometry;
    }

    /**
     * Rimuove la dimensione Z dalle coordinate (terzo elemento di ogni punto)
     */
    public static function removeZDimension($coordinates): array
    {
        if (! is_array($coordinates)) {
            return $coordinates;
        }

        // Se è un punto [x, y, z] o [x, y], rimuovi Z
        if (is_numeric($coordinates[0] ?? null)) {
            return array_slice($coordinates, 0, 2);
        }

        // Altrimenti ricorri sugli array annidati
        return array_map(function ($item) {
            return OsmfeaturesGeometryUpdateTrait::removeZDimension($item);
        }, $coordinates);
    }
}
