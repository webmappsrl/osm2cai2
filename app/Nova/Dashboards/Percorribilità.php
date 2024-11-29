<?php

namespace App\Nova\Dashboards;

use App\Models\User;
use Laravel\Nova\Dashboard;
use Illuminate\Support\Facades\Cache;

class Percorribilità extends Dashboard
{

    protected $user;

    public function __construct(User $user = null)
    {
        $this->user = $user;
    }

    public function label()
    {
        return 'Riepilogo Percorribilità';
    }
    /**
     * Get the cards for the dashboard.
     *
     * @return array
     */
    public function cards()
    {
        if ($this->user) {
            $region = $this->user->region;
            $areas = $this->user->area;
            $provinces = $this->user->provinces;
            $sectors = $this->user->sectors;
            $hikingRoutesSda34Collection = collect();
            $hikingRoutesSda4Collection = collect();
            if ($region) {
                $hikingRoutesSda4 = Cache::remember('hikingRoutesSda4', 60, function () use ($region) {
                    return \App\Models\HikingRoute::select('issues_status')
                        ->where('osm2cai_status', 4)
                        ->whereHas('regions', function ($query) use ($region) {
                            $query->where('regions.id', $region->id);
                        })
                        ->get();
                });
                $hikingRoutesSda34 = Cache::remember('hikingRoutesSda34', 60, function () use ($region) {
                    return \App\Models\HikingRoute::select('issues_status')
                        ->whereIn('osm2cai_status', [3, 4])
                        ->whereHas('regions', function ($query) use ($region) {
                            $query->where('regions.id', $region->id);
                        })
                        ->get();
                });
                $hikingRoutesSda4Collection = $hikingRoutesSda4Collection->merge($hikingRoutesSda4);
                $hikingRoutesSda34Collection = $hikingRoutesSda34Collection->merge($hikingRoutesSda34);

                return [
                    new \App\Nova\Metrics\Sda4IssueStatusPartition($hikingRoutesSda4),
                    new \App\Nova\Metrics\Sda3And4IssueStatusPartition($hikingRoutesSda34)
                ];
            }
            if ($areas) {
                $areasIds = $areas->pluck('id')->toArray();
                $hikingRoutesSda4 = Cache::remember('hikingRoutesSda4', 60, function () use ($areasIds) {
                    return \App\Models\HikingRoute::select('issues_status')
                        ->where('osm2cai_status', 4)
                        ->whereHas('areas', function ($query) use ($areasIds) {
                            $query->whereIn('areas.id', $areasIds);
                        })
                        ->get();
                });
                $hikingRoutesSda34 = Cache::remember('hikingRoutesSda34', 60, function () use ($areas) {
                    return \App\Models\HikingRoute::select('issues_status')
                        ->whereIn('osm2cai_status', [3, 4])
                        ->whereHas('areas', function ($query) use ($areasIds) {
                            $query->whereIn('areas.id', $areasIds);
                        })
                        ->get();
                });
                $hikingRoutesSda4Collection = $hikingRoutesSda4Collection->merge($hikingRoutesSda4);
                $hikingRoutesSda34Collection = $hikingRoutesSda34Collection->merge($hikingRoutesSda34);

                return [
                    new \App\Nova\Metrics\Sda4IssueStatusPartition($hikingRoutesSda4),
                    new \App\Nova\Metrics\Sda3And4IssueStatusPartition($hikingRoutesSda34)
                ];
            }
            if ($provinces) {
                $provincesIds = $provinces->pluck('id')->toArray();
                $hikingRoutesSda4 = Cache::remember('hikingRoutesSda4', 60, function () use ($provincesIds) {
                    return \App\Models\HikingRoute::select('issues_status')
                        ->where('osm2cai_status', 4)
                        ->whereHas('provinces', function ($query) use ($provincesIds) {
                            $query->whereIn('provinces.id', $provincesIds);
                        })
                        ->get();
                });
                $hikingRoutesSda34 = Cache::remember('hikingRoutesSda34', 60, function () use ($provincesIds) {
                    return \App\Models\HikingRoute::select('issues_status')
                        ->whereIn('osm2cai_status', [3, 4])
                        ->whereHas('provinces', function ($query) use ($provincesIds) {
                            $query->whereIn('provinces.id', $provincesIds);
                        })
                        ->get();
                });
                $hikingRoutesSda4Collection = $hikingRoutesSda4Collection->merge($hikingRoutesSda4);
                $hikingRoutesSda34Collection = $hikingRoutesSda34Collection->merge($hikingRoutesSda34);

                return [
                    new \App\Nova\Metrics\Sda4IssueStatusPartition($hikingRoutesSda4),
                    new \App\Nova\Metrics\Sda3And4IssueStatusPartition($hikingRoutesSda34)
                ];
            }
            if ($sectors) {
                $sectorsIds = $sectors->pluck('id')->toArray();
                $hikingRoutesSda4 = Cache::remember('hikingRoutesSda4', 60, function () use ($sectorsIds) {
                    return \App\Models\HikingRoute::select('issues_status')
                        ->where('osm2cai_status', 4)
                        ->whereHas('sectors', function ($query) use ($sectorsIds) {
                            $query->whereIn('sectors.id', $sectorsIds);
                        })
                        ->get();
                });
                $hikingRoutesSda34 = Cache::remember('hikingRoutesSda34', 60, function () use ($sectorsIds) {
                    return \App\Models\HikingRoute::select('issues_status')
                        ->whereIn('osm2cai_status', [3, 4])
                        ->whereHas('sectors', function ($query) use ($sectorsIds) {
                            $query->whereIn('sectors.id', $sectorsIds);
                        })
                        ->get();
                });
                $hikingRoutesSda4Collection = $hikingRoutesSda4Collection->merge($hikingRoutesSda4);
                $hikingRoutesSda34Collection = $hikingRoutesSda34Collection->merge($hikingRoutesSda34);

                return [
                    new \App\Nova\Metrics\Sda4IssueStatusPartition($hikingRoutesSda4),
                    new \App\Nova\Metrics\Sda3And4IssueStatusPartition($hikingRoutesSda34)
                ];
            }
        } else {
            $hikingRoutesSda4 = Cache::remember('hikingRoutesSda4', 60, function () {
                return \App\Models\HikingRoute::select('issues_status')
                    ->where('osm2cai_status', 4)
                    ->get();
            });
            $hikingRoutesSda34 = Cache::remember('hikingRoutesSda34', 60, function () {
                return \App\Models\HikingRoute::select('issues_status')
                    ->whereIn('osm2cai_status', [3, 4])
                    ->get();
            });
            return [
                new \App\Nova\Metrics\Sda4IssueStatusPartition($hikingRoutesSda4),
                new \App\Nova\Metrics\Sda3And4IssueStatusPartition($hikingRoutesSda34)
            ];
        }
    }

    /**
     * Get the URI key for the dashboard.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'percorribilità';
    }
}
