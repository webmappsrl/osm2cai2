<?php

namespace App\Traits;

use App\Models\HikingRoute;
use Illuminate\Support\Facades\DB;

trait IntersectingRouteStats
{
    public function getRouteStats()
    {
        $stats = [
            'tot1' => 0,
            'tot2' => 0,
            'tot3' => 0,
            'tot4' => 0,
        ];

        foreach ($this->hikingRoutes as $route) {
            if ($route->osm2cai_status >= 1 && $route->osm2cai_status <= 4) {
                $stats['tot'.$route->osm2cai_status]++;
            }
        }

        return (object) [
            'id' => $this->id,
            'full_code' => isset($this->full_code) ? $this->full_code : $this->osmfeatures_data['properties']['osm_tags']['short_name'] ?? $this->osmfeatures_data['properties']['osm_tags']['ref'] ?? null,
            'tot1' => $stats['tot1'],
            'tot2' => $stats['tot2'],
            'tot3' => $stats['tot3'],
            'tot4' => $stats['tot4'],
            'num_expected' => $this->num_expected,
        ];
    }

    public static function getStatsForIds(array $ids)
    {
        return static::whereIn('id', $ids)
            ->get()
            ->map(function ($model) {
                return $model->getRouteStats();
            });
    }
}
