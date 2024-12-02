<?php

namespace App\Http\Controllers;

use App\Models\HikingRoute;
use App\Models\Region;
use App\Models\Sector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

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
        if (! class_exists($modelClass)) {
            return response()->json(['error' => 'Model not found'], 404);
        }

        // Find the resource by ID
        $model = $modelClass::find($id);
        if (! $model) {
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
            $geometry = DB::select('SELECT ST_AsGeoJSON(geometry) as geom FROM ' . $resource->getTable() . ' WHERE id = ?', [$resource->id])[0];

            $geojson['features'][] = [
                'type' => 'Feature',
                'geometry' => json_decode($geometry->geom),
                'properties' => $this->getResourceProperties($resource, $modelType),
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
        switch (strtolower($modelType)) {
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
            case 'sector':
                return [
                    'id' => $model->id,
                    'name' => $model->name,
                    'area' => $model->area->name ?? null,
                    'province' => $model->area->province->name ?? null,
                    'region' => $model->area->province->region->name ?? null,
                    'geojson_url' => url("api/geojson/{$modelType}/{$model->id}"),
                    'shapefile_url' => url("api/shapefile/{$modelType}/{$model->id}"),
                    'kml' => url("api/kml/{$modelType}/{$model->id}"),
                ];
            case 'club':
                return [
                    'id' => $model->id,
                    'name' => $model->name,
                    'region' => $model->region->name ?? null,
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
        switch (strtolower($modelType)) {
            case 'region':
                $sectors = $model->sectorsIds();

                return Sector::whereIn('id', $sectors)
                    ->get();
            case 'province':
                $sectors = $model->sectorsIds();

                return Sector::whereIn('id', $sectors)
                    ->get();
            case 'area':
                $sectors = $model->sectorsIds();

                return Sector::whereIn('id', $sectors)
                    ->get();
            case 'club':
                $hikingRoutes = $model->hikingRoutes()->get();

                return HikingRoute::whereIn('id', $hikingRoutes->pluck('id'))
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
    private function getResourceProperties($resource, $modelType)
    {
        if ($resource instanceof HikingRoute) {
            $geometry = DB::select('SELECT ST_AsGeoJSON(ST_ForceRHR(geometry)) as geom FROM hiking_routes WHERE id = ?;', [$resource->id]);
            $osmfeaturesDataProperties = $resource->osmfeatures_data['properties'];

            $name = $resource->name ? $resource->name . ' - ' . $resource->ref : $resource->ref;

            $regions = $resource->regions->pluck('name')->implode(', ');
            $provinces = $resource->provinces->pluck('name')->implode(', ');
            $areas = $resource->areas->pluck('name')->implode(', ');
            $clubs = $resource->clubs->pluck('name')->implode(', ');

            $userName = $resource->user?->name ?? '';

            return [
                'id' => $resource->id,
                'name' => $name,
                'user' => $userName,
                'relation_id' => $osmfeaturesDataProperties['osm_id'] ?? '',
                'ref' => $resource->ref ?? '',
                'source_ref' => $osmfeaturesDataProperties['source_ref'] ?? '',
                'difficulty' => $resource->cai_scale ?? '',
                'from' => $osmfeaturesDataProperties['from'] ?? '',
                'to' => $osmfeaturesDataProperties['to'] ?? '',
                'regions' => $regions,
                'provinces' => $provinces,
                'areas' => $areas,
                'sector' => $resource->mainSector()->full_code ?? '',
                'clubs' => $clubs,
                'last_updated' => $resource->updated_at->format('Y-m-d'),
            ];
        }

        return [
            'id' => $resource->id,
            'name' => $resource->name ?? '',
            'area' => $resource->area->name ?? '',
            'province' => $resource->area->province->name ?? '',
            'region' => $resource->area->province->region->name ?? '',
            'geojson_url' => url("api/geojson/{$modelType}/{$resource->id}"),
            'shapefile_url' => url("api/shapefile/{$modelType}/{$resource->id}"),
            'kml' => url("api/kml/{$modelType}/{$resource->id}"),
        ];
    }
}
