<?php

namespace App\Nova\Metrics;

use App\Models\User;
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
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        $keys = ['Regione', 'Provincia', 'Area', 'Settore', 'Sconosciuto'];
        $regionUsers = 0;
        $provinceUsers = 0;
        $areaUsers = 0;
        $sectorUsers = 0;
        $unknownUsers = 0;

        foreach ($this->users as $user) {
            if ($user->region_id !== null) {
                $regionUsers++;
            }
            if (count($user->provinces) > 0) {
                $provinceUsers++;
            }
            if (count($user->areas) > 0) {
                $areaUsers++;
            }
            if (count($user->sectors) > 0) {
                $sectorUsers++;
            }
            if ($user->region_id === null && count($user->provinces) == 0 && count($user->areas) == 0 && count($user->sectors) == 0) {
                $unknownUsers++;
            }
        }
        $result = array_combine($keys, [$regionUsers, $provinceUsers, $areaUsers, $sectorUsers, $unknownUsers]);

        return $this->result($result);
    }

    /**
     * Determine for how many minutes the metric should be cached.
     *
     * @return  \DateTimeInterface|\DateInterval|float|int
     */
    public function cacheFor()
    {
        return now()->addMinutes(5);
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
