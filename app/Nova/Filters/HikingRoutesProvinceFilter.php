<?php

namespace App\Nova\Filters;

use App\Models\Province;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class HikingRoutesProvinceFilter extends Filter
{
    /**
     * The filter's component.
     *
     * @var string
     */
    public $component = 'select-filter';

    public $name = 'Province';

    /**
     * Apply the filter to the given query.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(NovaRequest $request, $query, $value)
    {
        return $query->whereHas('provinces', function ($query) use ($value) {
            $query->where('province_id', $value);
        });
    }

    /**
     * Get the filter's available options.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function options(NovaRequest $request)
    {
        $options = [];
        if (auth()->user()->hasRole('Regional Referent')) {
            $provinces = Province::where('region_id', auth()->user()->region->id)->orderBy('name')->get();
            foreach ($provinces as $item) {
                $options[$item->name] = $item->id;
            }
        } else {
            foreach (Province::orderBy('name')->get() as $item) {
                $options[$item->name] = $item->id;
            }
        }
        return $options;
    }
}
