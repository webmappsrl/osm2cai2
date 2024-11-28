<?php

namespace App\Nova\Metrics;

use App\Models\HikingRoute;
use Laravel\Nova\Metrics\Trend;
use Laravel\Nova\Http\Requests\NovaRequest;

class ValidatedHrPerMonth extends Trend
{

    /**
     * Get the displayable name of the metric
     *
     * @return string
     */
    public function name()
    {
        return 'Validated Hiking Routes per month';
    }
    /**
     * Calculate the value of the metric.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        return $this->countByMonths($request, HikingRoute::where('osm2cai_status', 4))
            ->format('0,0');
    }

    /**
     * Get the ranges available for the metric.
     *
     * @return array
     */
    public function ranges()
    {
        return [
            1 => __('1 Month'),
            3 => __('3 Months'),
            6 => __('6 Months'),
            9 => __('9 Months'),
            12 => __('12 Months'),
            24 => __('24 Months'),
            36 => __('36 Months'),
        ];
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
        return 'validated-hr-per-month';
    }
}
