<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class CaiHutsHRFilter extends Filter
{
    /**
     * The filter's component.
     *
     * @var string
     */
    public $component = 'select-filter';

    public $name = 'CAI Huts';

    /**
     * Apply the filter to the given query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(NovaRequest $request, $query, $value)
    {
        return $query->when($value === 'with_cai_huts', function ($query) {
            return $query->whereHas('nearbyCaiHuts');
        })->when($value === 'without_cai_huts', function ($query) {
            return $query->whereDoesntHave('nearbyCaiHuts');
        });
    }

    /**
     * Get the filter's available options.
     *
     * @return array
     */
    public function options(NovaRequest $request)
    {
        return [
            'With Cai Huts' => 'with_cai_huts',
            'Without Cai Huts' => 'without_cai_huts',
        ];
    }
}
