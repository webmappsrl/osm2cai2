<?php

namespace App\Nova\Metrics;

use Illuminate\Support\Facades\DB;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;

class UserDistributionByRegion extends Partition
{
    protected $users;

    public function __construct(iterable $users)
    {
        $this->users = $users;
    }

    /**
     * Calculate the value of the metric.
     *
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        // Calcola gli utenti sconosciuti
        $this->users['Unknown'] = DB::table('users')
            ->whereNull('region_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('province_user')
                    ->whereRaw('province_user.user_id = users.id');
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('area_user')
                    ->whereRaw('area_user.user_id = users.id');
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('sector_user')
                    ->whereRaw('sector_user.user_id = users.id');
            })
            ->count();

        return $this->result($this->users);
    }

    /**
     * Determine for how many minutes the metric should be cached.
     *
     * @return \DateTimeInterface|\DateInterval|float|int
     */
    public function cacheFor()
    {
        return now()->addDay();
    }

    /**
     * Get the URI key for the metric.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'user-distribution-by-region';
    }
}
