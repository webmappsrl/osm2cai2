<?php

namespace App\Nova\Metrics;

use App\Models\EcPoi;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Trend;
use Laravel\Nova\Metrics\TrendDateExpressionFactory;
use Laravel\Nova\Nova;

class EcPoisTrend extends Trend
{
    public $name = 'Distribuzione temporale nuovi POI';

    public $width = '1/2';

    /**
     * Calculate the value of the metric.
     *
     * @param  NovaRequest  $request
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        return $this->countByDays($request, EcPoi::class);
    }

    /**
     * Get default timezone.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    private function getDefaultTimezone($request)
    {
        return $request->timezone ?? config('app.timezone');
    }

    /**
     * Return a value result showing a aggregate over time.
     *
     * @param  NovaRequest  $request
     * @param  Builder|class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @param  string  $unit
     * @param  string  $function
     * @param  \Illuminate\Database\Query\Expression|string  $column
     * @param  string|null  $dateColumn
     * @return \Laravel\Nova\Metrics\TrendResult
     */
    protected function aggregate($request, $model, $unit, $function, $column, $dateColumn = null)
    {
        $query = $model instanceof Builder ? $model : (new $model)->newQuery();

        $timezone = Nova::resolveUserTimezone($request) ?? $this->getDefaultTimezone($request);

        $expression = (string) TrendDateExpressionFactory::make(
            $query,
            $dateColumn = $dateColumn ?? $query->getModel()->getQualifiedCreatedAtColumn(),
            $unit,
            $timezone
        );

        $possibleDateResults = $this->getAllPossibleDateResults(
            $startingDate = $this->getAggregateStartingDate($request, $unit, $timezone),
            $endingDate = CarbonImmutable::now($timezone),
            $unit,
            $request->twelveHourTime === 'true',
            $request->range
        );

        $wrappedColumn = $query->getQuery()->getGrammar()->wrap($column);

        $results = $query
            ->select(DB::raw("{$expression} as date_result, {$function}({$wrappedColumn}) as aggregate"))
            // ->tap(function ($query) use ($request) {
            //     return $this->applyFilterQuery($request, $query);
            // })
            ->whereBetween(
                $dateColumn,
                $this->formatQueryDateBetween([$startingDate, $endingDate])
            )->groupBy(DB::raw($expression))
            ->orderBy('date_result')
            ->get();

        $possibleDateKeys = array_keys($possibleDateResults);

        $results = array_merge($possibleDateResults, $results->mapWithKeys(function ($result) use ($request, $unit) {
            return [$this->formatAggregateResultDate(
                $result->date_result,
                $unit,
                $request->twelveHourTime === 'true'
            ) => round($result->aggregate ?? 0, $this->roundingPrecision, $this->roundingMode)];
        })->reject(function ($value, $key) use ($possibleDateKeys) {
            return ! in_array($key, $possibleDateKeys);
        })->all());

        return $this->result(Arr::last($results))->trend(
            $results
        );
    }

    /**
     * Get the ranges available for the metric.
     *
     * @return array
     */
    public function ranges()
    {
        return [
            30 => __('30 Days'),
            60 => __('60 Days'),
            90 => __('90 Days'),
        ];
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
        return 'ec-pois-trend';
    }

    /**
     * Get the appropriate cache key for the metric.
     *
     * @param  NovaRequest  $request
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
