<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use App\Models\Region;  // Add other required models

class GeojsonController extends Controller
{
    /**
     * Returns GeoJSON for a given model and ID.
     *
     * @param string $modelType
     * @param string $id
     * @return \Illuminate\Http\Response
     */
    public function download(string $modelType, string $id)
    {
        // Dynamically load the model based on type
        $modelClass = 'App\\Models\\' . ucfirst($modelType);

        // Verify that the model exists
        if (!class_exists($modelClass)) {
            return response()->json(['error' => 'Model not found'], 404);
        }

        // Find the resource by ID
        $model = $modelClass::find($id);
        if (!$model) {
            return response()->json(['error' => ucfirst($modelType) . ' ' . $id . ' not found'], 404);
        }

        // Initialize GeoJSON structure
        $geojson = [
            'type' => 'FeatureCollection',
            'features' => [],
            'properties' => $this->getModelProperties($modelType, $model),
        ];

        // Get related resources, if they exist
        $relatedResources = $this->getRelatedResources($modelType, $model);

        foreach ($relatedResources as $resource) {
            $geojson['features'][] = [
                'type' => 'Feature',
                'geometry' => json_decode($resource->geom),
                'properties' => $this->getResourceProperties($resource),
            ];
        }

        // Settings for GeoJSON file download
        $headers = [
            'Content-type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $id . '.geojson"',
        ];

        return response(json_encode($geojson), 200, $headers);
    }

    /**
     * Returns the properties of the main model.
     *
     * @param string $modelType
     * @param object $model
     * @return array
     */
    private function getModelProperties(string $modelType, $model)
    {
        // Perform dynamic property mapping based on model type
        switch ($modelType) {
            case 'region':
                return [
                    'id' => $model->id,
                    'name' => $model->name,
                    'geojson_url' => url("api/geojson/{$modelType}/{$model->id}"),
                    'shapefile_url' => url("api/shapefile/{$modelType}/{$model->id}"),
                    'kml' => url("api/kml/{$modelType}/{$model->id}"),
                ];
            case 'province':
                return [
                    'id' => $model->id,
                    'name' => $model->name,
                    'region' => $model->region->name,
                    'geojson_url' => url("api/geojson/{$modelType}/{$model->id}"),
                    'shapefile_url' => url("api/shapefile/{$modelType}/{$model->id}"),
                    'kml' => url("api/kml/{$modelType}/{$model->id}"),
                ];
            case 'area':
                return [
                    'id' => $model->id,
                    'name' => $model->name,
                    'province' => $model->province->name ?? null,
                    'region' => $model->province->region->name ?? null,
                    'geojson_url' => url("api/geojson/{$modelType}/{$model->id}"),
                    'shapefile_url' => url("api/shapefile/{$modelType}/{$model->id}"),
                    'kml' => url("api/kml/{$modelType}/{$model->id}"),
                ];
            default:
                return [];
        }
    }

    /**
     * Returns related resources for the specified model.
     *
     * @param string $modelType
     * @param object $model
     * @return array
     */
    private function getRelatedResources(string $modelType, $model)
    {
        // Execute query to get related resources based on model type
        switch ($modelType) {
            case 'region':
                // Get sectors related to the region
                $sectors = $model->getSectorIds();
                return Sector::whereIn('id', $sectors)
                    ->select('id', DB::raw('ST_AsGeoJSON(ST_ForceRHR(geometry)) as geom'))
                    ->get();
            case 'province':
                // Get sectors related to the province
                $sectors = $model->getSectorIds();
                return Sector::whereIn('id', $sectors)
                    ->select('id', DB::raw('ST_AsGeoJSON(ST_ForceRHR(geometry)) as geom'))
                    ->get();
            default:
                return [];
        }
    }

    /**
     * Returns properties for each related resource.
     *
     * @param object $resource
     * @return array
     */
    private function getResourceProperties($resource)
    {
        return [
            'id' => $resource->id,
            'name' => $resource->name,
            'area' => $resource->area->name,
            'province' => $resource->area->province->name,
            'region' => $resource->area->province->region->name,
            'geojson_url' => route('api.geojson.sector', ['id' => $resource->id]),
            'shapefile_url' => route('api.shapefile.sector', ['id' => $resource->id]),
            'kml' => route('api.kml.sector', ['id' => $resource->id]),
        ];
    }
}
