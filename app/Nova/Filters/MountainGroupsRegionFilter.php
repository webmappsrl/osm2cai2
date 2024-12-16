<?php

namespace App\Nova\Filters;

use App\Models\Region;
use Illuminate\Http\Request;
use Laravel\Nova\Filters\Filter;

class MountainGroupsRegionFilter extends Filter
{
    /**
     * The filter's component.
     *
     * @var string
     */
    public $component = 'select-filter';

    public $name = 'Regione';

    /**
     * Apply the filter to the given query.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(Request $request, $query, $value)
    {
        return $query->whereHas('regions', function ($query) use ($value) {
            $query->where('region_id', $value);
        });
    }

    /**
     * Get the filter's available options.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function options(Request $request)
    {
        $regions = Region::all();

        return $regions->mapWithKeys(function ($region) {
            return [$region->name => $region->id];
        })->toArray();
    }
}
