<?php

namespace App\Services;

use App\Models\Region;
use App\Models\HikingRoute;
use Illuminate\Support\Facades\DB;

class IntersectionService
{
    public function calculateIntersections($region = null, $hikingRoute = null)
    {
        if ($region) {
            return $this->calculateForRegion($region);
        } elseif ($hikingRoute) {
            return $this->calculateForHikingRoute($hikingRoute);
        } else {
            return $this->calculateForAllRegions();
        }
    }

    public function calculateForRegion(Region $region)
    {
        $intersectingRoutes = HikingRoute::whereRaw("ST_Intersects(ST_GeomFromGeoJSON(osmfeatures_data->>'geometry'), ?::geography)", [$region->geometry])
            ->get();

        $hikingRoutesData = $intersectingRoutes->mapWithKeys(function ($route) {
            $osmfeaturesData = json_decode($route->osmfeatures_data, true);
            return [$route->osmfeatures_id => [
                'osm2cai_status' => $route['osm2cai_status'] ?? null,
                'validation_date' => $route['validation_date'] ?? null,
                'issues_status' => $route['issues_status'] ?? null,
                'issues_last_update' => $route['issues_last_update'] ?? null,
                'issues_user_id' => $route['issues_user_id'] ?? null,
                'issues_chronology' => $route['issues_chronology'] ?? null,
                'issues_description' => $route['issues_description'] ?? null,
                'description_cai_it' => $route['description_cai_it'] ?? null,
            ]];
        })->toArray();

        $region->update(['hiking_routes_intersecting' => $hikingRoutesData]);

        return $hikingRoutesData;
    }

    public function calculateForHikingRoute(HikingRoute $hikingRoute)
    {
        $intersectingRegions = Region::whereRaw("ST_Intersects(geometry, ST_GeomFromGeoJSON(?))", [$hikingRoute->osmfeatures_data['geometry']])
            ->get();

        foreach ($intersectingRegions as $region) {
            $this->calculateForRegion($region);
        }
    }

    public function calculateForAllRegions()
    {
        $regions = Region::all();
        foreach ($regions as $region) {
            $this->calculateForRegion($region);
        }
    }
}
