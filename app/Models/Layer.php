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
        // Pulisce features precedenti per evitare duplicati
        $this->clearAdditionalFeaturesForMap();

        // Recupera tutte le hiking routes associate al layer tramite la tabella pivot layerables
        $hikingRoutes = $this->ecTracks()
            ->select('hiking_routes.id', 'hiking_routes.name', 'hiking_routes.geometry')
            ->where('layerables.layer_id', $this->id)
            ->where('layerables.layerable_type', HikingRoute::class)
            ->limit(50)
            ->get();

        // Aggiungi le hiking routes come features aggiuntive
        foreach ($hikingRoutes as $hikingRoute) {
            $routeFeature = $this->getFeatureMap($hikingRoute->geometry);
            $properties = [
                'tooltip' => $hikingRoute->name.' (hiking route)',
                'link' => url('/resources/hiking-routes/'.$hikingRoute->id),
                'strokeColor' => 'red',
                'strokeWidth' => 2,
            ];
            $routeFeature['properties'] = $properties;
            $this->addFeaturesForMap([$routeFeature]);
        }

        // Il Layer non ha una geometria propria, quindi restituiamo solo le features aggiuntive
        // Generiamo un GeoJSON vuoto e aggiungiamo solo le hiking routes
        return [
            'type' => 'FeatureCollection',
            'features' => $this->getAdditionalFeaturesForMap(),
        ];
    }

    public function getFeatureCollectionMap2(): array
    {
        $this->clearAdditionalFeaturesForMap();
        $sql = $this->buildUltraOptimizedSQL($this);
        $hikingRoutes = DB::select($sql);

        return $hikingRoutes;
    }

    private function buildUltraOptimizedSQL($layer)
    {
        $layerId = $layer->id;

        $simplifySelect = 'ST_AsGeoJSON(hr.geometry) as geometry';

        return "
            SELECT z
                hr.id,
                hr.name,
                hr.properties,
                {$simplifySelect}
            FROM hiking_routes hr
            INNER JOIN layerables l ON hr.id = l.layerable_id
            WHERE l.layer_id = {$layerId}
                AND l.layerable_type = 'App\\Models\\HikingRoute'
                AND hr.geometry IS NOT NULL
                AND hr.geometry != ''
            ORDER BY hr.id
        ";
    }
}
