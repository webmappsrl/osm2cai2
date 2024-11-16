<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait OsmfeaturesGeometryUpdateTrait
{
    protected static function updateGeometry($model, $osmfeaturesData, $osmfeaturesId)
    {
        $updateData = [];


        if (!isset($osmfeaturesData['geometry'])) {
            Log::channel('wm-osmfeatures')->info('No geometry found for ' . class_basename($model) . ' ' . $osmfeaturesId);
            return $updateData;
        }
        // Format the new geometry
        $newGeometry = DB::selectOne(
            "SELECT ST_AsText(ST_GeomFromGeoJSON(?)) as geometry",
            [json_encode($osmfeaturesData['geometry'])]
        )->geometry;

        //if model geometry is null, set the new geometry
        if ($model->geometry === null) {
            $model->geometry = $newGeometry;
            $updateData['geometry'] = DB::raw("ST_GeomFromText('$newGeometry')");
            Log::channel('wm-osmfeatures')->info('Geometry updated for ' . class_basename($model) . ' ' . $osmfeaturesId);
            return $updateData;
        }

        // Compare the new geometry with the existing one
        $existingGeometry = DB::selectOne(
            "SELECT ST_AsText(?) as geometry",
            [$model->geometry]
        )->geometry;

        $geometryChanged = DB::selectOne(
            "SELECT NOT ST_Equals(ST_GeomFromText(?), ST_GeomFromText(?)) as changed",
            [$newGeometry, $existingGeometry]
        )->changed;

        // Update the geometry only if it has changed
        if ($geometryChanged) {
            $updateData['geometry'] = DB::raw("ST_GeomFromText('$newGeometry')");
            Log::channel('wm-osmfeatures')->info('Geometry updated for ' . class_basename($model) . ' ' . $osmfeaturesId);
        } else {
            Log::channel('wm-osmfeatures')->info('No geometry change for ' . class_basename($model) . ' ' . $osmfeaturesId);
        }

        return $updateData;
    }
}
