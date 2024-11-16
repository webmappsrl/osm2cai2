<?php

namespace App\Services;

use App\Models\HikingRoute;
use App\Models\Region;
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
        $geometryJson = json_encode($hikingRoute->osmfeatures_data['geometry']);

        $intersectingRegions = Region::whereRaw('ST_Intersects(geometry, ST_GeomFromGeoJSON(?))', [$geometryJson])
            ->get();

        foreach ($intersectingRegions as $region) {
            $hikingRoutesIntersecting = $region->hiking_routes_intersecting ?? [];
            if (! in_array($hikingRoute->osmfeatures_id, $hikingRoutesIntersecting)) {
                $hikingRouteData = [$hikingRoute->osmfeatures_id => [
                    'osm2cai_status' => $hikingRoute['osm2cai_status'] ?? null,
                    'validation_date' => $hikingRoute['validation_date'] ?? null,
                    'issues_status' => $hikingRoute['issues_status'] ?? null,
                    'issues_last_update' => $hikingRoute['issues_last_update'] ?? null,
                    'issues_user_id' => $hikingRoute['issues_user_id'] ?? null,
                    'issues_chronology' => $hikingRoute['issues_chronology'] ?? null,
                    'issues_description' => $hikingRoute['issues_description'] ?? null,
                    'description_cai_it' => $hikingRoute['description_cai_it'] ?? null,
                ]];
                $hikingRoutesIntersecting = array_merge($hikingRoutesIntersecting, $hikingRouteData);
                $region->update(['hiking_routes_intersecting' => $hikingRoutesIntersecting]);
            }
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
