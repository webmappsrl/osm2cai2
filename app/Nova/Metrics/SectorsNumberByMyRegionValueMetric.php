<?php

namespace App\Nova\Metrics;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;

class SectorsNumberByMyRegionValueMetric extends Value
{
    public $name = '#sectors';

    /**
     * Calculate the value of the metric.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        // I assume that is regional referent (region_id !null)
        $count = 0;
        foreach ($request->user()->region->provinces as $province) {
            foreach ($province->areas as $area) {
                $count += count($area->sectors);
            }
        }
        return $this->result($count);
    }

    /**
     * Get the ranges available for the metric.
     *
     * @return array
     */
    public function ranges()
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
        return 'sectors-number-by-my-region-value-metricphp';
    }
}
