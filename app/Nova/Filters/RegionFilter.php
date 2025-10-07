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

    public function __construct()
    {
        $this->name = __('Region');
    }

    /**
     * Apply the filter to the given query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(NovaRequest $request, $query, $value)
    {
        $model = $query->getModel();

        if ($model instanceof \App\Models\Province || $model instanceof \App\Models\Club) {
            return $query->where('region_id', $value);
        }

        if ($model instanceof \App\Models\Sector) {
            return $query->whereHas('area.province', function ($query) use ($value) {
                $query->where('region_id', $value);
            });
        }

        if ($model instanceof \App\Models\User) {
            return $query->whereHas('region', function ($query) use ($value) {
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
