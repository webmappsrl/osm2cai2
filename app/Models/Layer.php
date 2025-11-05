<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Wm\WmPackage\Models\Layer as WmLayer;
use Wm\WmPackage\Nova\Fields\FeatureCollectionMap\src\FeatureCollectionMapTrait;

class Layer extends WmLayer
{
    use FeatureCollectionMapTrait;

    protected static function boot()
    {
        parent::boot();
        // Registra l'observer anche nel modello dell'applicazione
        Layer::observe(\Wm\WmPackage\Observers\LayerObserver::class);
    }

    /**
     * Get feature collection map for layer with all associated hiking routes
     *
     * @return array GeoJSON feature collection
     */
    public function getFeatureCollectionMap(): array
    {
        $this->clearAdditionalFeaturesForMap();

        $hikingRoutes = DB::select($this->getOptimizedHikingRoutes(), [$this->id, HikingRoute::class]);
        // Nova resource name
        $novaResourceName = $this->getNovaResourceName();

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
                        'link' => url('/resources/' . $novaResourceName . '/' . $hikingRoute->id),
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

    /**
     * Get the Nova resource name based on app_id
     */
    private function getNovaResourceName(): string
    {
        $resourceClass = $this->app_id == 1
            ? \App\Nova\HikingRoute::class
            : \App\Nova\EcTrack::class;

        return $resourceClass::uriKey();
    }
}
