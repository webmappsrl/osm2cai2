<?php

namespace App\Nova\Metrics;

use App\Models\Area;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;

class TotalAreasCount extends Value
{

    /**
     * Set card's label
     *
     * @return string
     */
    public function name()
    {
        return 'Numero Aree';
    }

    /**
     * Calculate the value of the metric.
     *
     * @param NovaRequest $request
     *
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        return $this->result(Area::all()->count());
    }

    /**
     * Get the ranges available for the metric.
     *
     * @return array
     */
    public function ranges(): array
    {
        return [];
    }

    /**
     * Determine for how many minutes the metric should be cached.
     *
     * @return  \DateTimeInterface|\DateInterval|float|int
     */
    public function cacheFor()
    {
        // return now()->addMinutes(5);
    }

    /**
     * Get the URI key for the metric.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'total-areas-number';
    }
}
