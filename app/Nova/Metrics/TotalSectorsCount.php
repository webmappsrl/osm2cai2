<?php

namespace App\Nova\Metrics;

use App\Models\Sector;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;

class TotalSectorsCount extends Value
{
    /**
     * Set card's label
     *
     * @return string
     */
    public function name()
    {
        return 'Numero Settori';
    }

    /**
     * Calculate the value of the metric.
     *
     *
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        return $this->result(Sector::count())->format('0,0');
    }

    /**
     * Get the ranges available for the metric.
     */
    public function ranges(): array
    {
        return [];
    }

    /**
     * Determine for how many minutes the metric should be cached.
     *
     * @return \DateTimeInterface|\DateInterval|float|int
     */
    public function cacheFor()
    {
        return now()->addMinutes(60 * 24);
    }

    /**
     * Get the URI key for the metric.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'total-sectors-number';
    }
}
