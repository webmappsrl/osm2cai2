<?php

namespace App\Nova\Metrics;

use App\Models\EcPoi;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;
use Laravel\Nova\Metrics\PartitionResult;

class EcPoisTypePartition extends Partition
{
    public function __construct()
    {
        $this->name = __('Distribuzione per campo type');
    }

    public $width = '1/2';

    /**
     * Calculate the value of the metric.
     *
     * @return mixed
     */
    public function calculate(NovaRequest $request): PartitionResult
    {
        return $this->result(
            EcPoi::query()
                ->select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->get()
                ->pluck('count', 'type')
                ->toArray()
        );
        // return $this->count($request, EcPoi::class, 'type')
        //     ->label(function ($value) {
        //         return $value;
        //     });
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
        return 'ec-pois-type-partition';
    }

    /**
     * Get the appropriate cache key for the metric.
     *
     * @return string
     */
    public function getCacheKey(NovaRequest $request)
    {
        // return sprintf(
        //     'nova.metric.%s.%s.%s.%s.%s.%s',
        //     $this->uriKey(),
        //     $request->input('range', 'no-range'),
        //     $request->input('timezone', 'no-timezone'),
        //     $request->input('twelveHourTime', 'no-12-hour-time'),
        //     $this->onlyOnDetail ? $request->findModelOrFail()->getKey() : 'no-resource-id',
        //     md5($request->input('filter', 'no-filter'))
        // );

        return 'nova.metric.'.$this->uriKey();
    }
}
