<?php

namespace App\Nova\Filters;

use App\Models\Region;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class RegionFilter extends Filter
{
    /**
     * The filter's component.
     *
     * @var string
     */
    public $component = 'select-filter';

    public $name = 'Region';

    /**
     * Apply the filter to the given query.
     *
     * @param  NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(NovaRequest $request, $query, $value)
    {
        $model = $query->getModel();

        if ($model instanceof \App\Models\Province) {
            return $query->where('region_id', $value);
        }

        if ($model instanceof \App\Models\Sector) {
            return $query->whereHas('area.province', function ($query) use ($value) {
                $query->where('region_id', $value);
            });
        }

        return $query->whereHas('regions', function ($query) use ($value) {
            $query->where('region_id', $value);
        });
    }

    /**
     * Get the filter's available options.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function options(NovaRequest $request)
    {
        $options = [];
        foreach (Region::all() as $region) {
            $options[$region->name] = $region->id;
        }

        return $options;
    }
}
