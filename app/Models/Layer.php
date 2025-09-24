<?php

namespace App\Models;

use App\Nova\Fields\FeatureCollectionMap\src\FeatureCollectionMapTrait;
use Illuminate\Support\Facades\DB;
use Wm\WmPackage\Models\Layer as WmLayer;

class Layer extends WmLayer
{
    use FeatureCollectionMapTrait;

    /**
     * Get feature collection map for layer with all associated hiking routes
     *
     * @return array GeoJSON feature collection
     */
    public function getFeatureCollectionMap(): array
    {
        $this->clearAdditionalFeaturesForMap();

        $hikingRoutes = DB::select($this->getOptimizedHikingRoutes(), [$this->id, HikingRoute::class]);

        foreach ($hikingRoutes as $hikingRoute) {
            $geometry = json_decode($hikingRoute->geometry, true);

            // Decodifica il JSON del nome e estrai la traduzione italiana o la prima disponibile
            $nameData = json_decode($hikingRoute->name, true);

            // Priorità: 1) Italiano, 2) Prima disponibile, 3) Nome non disponibile
            $hikingRouteName = $nameData['it'] ?? (is_array($nameData) && ! empty($nameData) ? reset($nameData) : 'Nome non disponibile');

            if ($geometry) {
                $routeFeature = [
                    'type' => 'Feature',
                    'geometry' => $geometry,
                    'properties' => [
                        'tooltip' => $hikingRouteName,
                        'link' => url('/resources/hiking-routes/'.$hikingRoute->id),
                        'strokeColor' => 'red',
                        'strokeWidth' => 2,
                    ],
                ];
                $this->addFeaturesForMap([$routeFeature]);
            }
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $this->getAdditionalFeaturesForMap(),
        ];
    }

    private function getOptimizedHikingRoutes()
    {
        $sql = "
        SELECT 
            hr.id,
            hr.name,
            hr.properties,
            ST_AsGeoJSON(hr.geometry) as geometry
        FROM hiking_routes hr
        INNER JOIN layerables l ON hr.id = l.layerable_id
        WHERE l.layer_id = ?
            AND l.layerable_type = ?
            AND hr.geometry IS NOT NULL
            AND hr.geometry != ''
        ORDER BY hr.id
    ";

        return $sql;
    }
}
