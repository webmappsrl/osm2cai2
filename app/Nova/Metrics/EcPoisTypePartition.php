<?php

namespace App\Nova\Metrics;

use App\Models\EcPoi;
use Laravel\Nova\Metrics\Partition;
use Laravel\Nova\Metrics\PartitionResult;
use Laravel\Nova\Http\Requests\NovaRequest;

class EcPoisTypePartition extends Partition
{
    public $name = 'Distribuzione per campo type';

    public $width = '1/2';

    /**
     * Calculate the value of the metric.
     *
     * @param  NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request): PartitionResult
    {
        return $this->count($request, EcPoi::class, 'type')
            ->label(function ($value) {
                return $value;
            });
    }

    /**
     * Determine for how many minutes the metric should be cached.
     *
     * @return  \DateTimeInterface|\DateInterval|float|int
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
        return 'ec-pois-type-partition';
    }
}
