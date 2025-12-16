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
        self::observe(\Wm\WmPackage\Observers\LayerObserver::class);
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
            // La geometria è già GeoJSON dalla query, quindi possiamo decodificarla direttamente
            $geometry = json_decode($hikingRoute->geometry, true);

            if (! $geometry) {
                continue;
            }

            // Decodifica il JSON del nome una sola volta
            $nameData = $hikingRoute->name ? json_decode($hikingRoute->name, true) : null;

            // Priorità: 1) Italiano, 2) Prima disponibile, 3) Nome non disponibile
            $hikingRouteName = $nameData['it'] ?? (is_array($nameData) && ! empty($nameData) ? reset($nameData) : 'Nome non disponibile');

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

        return [
            'type' => 'FeatureCollection',
            'features' => $this->getAdditionalFeaturesForMap(),
        ];
    }

    private function getOptimizedHikingRoutes()
    {
        // Query ottimizzata: filtra prima sulla tabella layerables (più piccola) e poi fa il join
        // Questo sfrutta meglio gli indici esistenti
        // Nota: per geography type, ST_IsEmpty non è disponibile, quindi usiamo solo IS NOT NULL
        $sql = "
        SELECT 
            hr.id,
            hr.name,
            hr.properties,
            ST_AsGeoJSON(hr.geometry) as geometry
        FROM layerables l
        INNER JOIN hiking_routes hr ON hr.id = l.layerable_id
        WHERE l.layer_id = ?
            AND l.layerable_type = ?
            AND hr.geometry IS NOT NULL
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
