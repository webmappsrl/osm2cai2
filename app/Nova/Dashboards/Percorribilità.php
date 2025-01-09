<?php

namespace App\Nova\Dashboards;

use App\Models\User;
use App\Models\HikingRoute;
use App\Nova\Metrics\IssueStatusPartition;
use Illuminate\Support\Facades\Cache;
use Laravel\Nova\Dashboard;

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
        $hikingRoutesSda4 = $this->getHikingRoutes(4);
        $hikingRoutesSda34 = $this->getHikingRoutes([3, 4]);

        return [
            new IssueStatusPartition($hikingRoutesSda4, 'Percorribilità SDA 4', 'sda4-issue-status-partition'),
            new IssueStatusPartition($hikingRoutesSda34, 'Percorribilità SDA 3 e 4', 'sda3-and-4-issue-status-partition'),
        ];
    }

    /**
     * Get hiking routes filtered by status and user's territory
     * 
     * @param int|array $status
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getHikingRoutes($status)
    {
        $cacheKey = is_array($status) ? 'hikingRoutesSda' . implode('', $status) : 'hikingRoutesSda' . $status;

        return Cache::remember($cacheKey, 60, function () use ($status) {
            $query = HikingRoute::select('issues_status');

            if (is_array($status)) {
                $query->whereIn('osm2cai_status', $status);
            } else {
                $query->where('osm2cai_status', $status);
            }

            if ($this->user) {
                $query->where(function ($q) {
                    if ($this->user->region) {
                        $q->orWhereHas(
                            'regions',
                            fn($query) =>
                            $query->where('regions.id', $this->user->region->id)
                        );
                    }

                    if ($this->user->area->count()) {
                        $q->orWhereHas(
                            'areas',
                            fn($query) =>
                            $query->whereIn('areas.id', $this->user->area->pluck('id'))
                        );
                    }

                    if ($this->user->provinces->count()) {
                        $q->orWhereHas(
                            'provinces',
                            fn($query) =>
                            $query->whereIn('provinces.id', $this->user->provinces->pluck('id'))
                        );
                    }

                    if ($this->user->sectors->count()) {
                        $q->orWhereHas(
                            'sectors',
                            fn($query) =>
                            $query->whereIn('sectors.id', $this->user->sectors->pluck('id'))
                        );
                    }
                });
            }

            return $query->get();
        });
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
