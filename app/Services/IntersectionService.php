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

    protected function calculateForRegion(Region $region)
    {
        $intersectingRoutes = HikingRoute::select(
            'osmfeatures_id',
            'osm2cai_status',
            'validation_date',
            'issues_status',
            'issues_last_update',
            'issues_user_id',
            'issues_chronology',
            'issues_description',
            'description_cai_it'
        )
            ->whereRaw("ST_Intersects(ST_GeomFromGeoJSON(osmfeatures_data->>'geometry'), ?::geography)", [$region->geometry])
            ->get();

        $hikingRoutesData = $intersectingRoutes->mapWithKeys(function ($route) {
            return [$route->osmfeatures_id => [
                'osm2cai_status' => $route->osm2cai_status,
                'validation_date' => $route->validation_date,
                'issues_status' => $route->issues_status,
                'issues_last_update' => $route->issues_last_update,
                'issues_user_id' => $route->issues_user_id,
                'issues_chronology' => $route->issues_chronology,
                'issues_description' => $route->issues_description,
                'description_cai_it' => $route->description_cai_it,
            ]];
        })->toArray();

        $region->update(['hiking_routes_intersecting' => $hikingRoutesData]);

        return $hikingRoutesData;
    }

    protected function calculateForHikingRoute(HikingRoute $hikingRoute)
    {
        $intersectingRegions = Region::whereRaw("ST_Intersects(geometry, ST_GeomFromGeoJSON(?))", [$hikingRoute->osmfeatures_data['geometry']])
            ->get();

        foreach ($intersectingRegions as $region) {
            $this->calculateForRegion($region);
        }
    }

    protected function calculateForAllRegions()
    {
        $regions = Region::all();
        foreach ($regions as $region) {
            $this->calculateForRegion($region);
        }
    }
}
