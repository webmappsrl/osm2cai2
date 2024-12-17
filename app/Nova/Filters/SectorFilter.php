<?php

namespace App\Nova\Filters;

use App\Models\Sector;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class SectorFilter extends Filter
{
    /**
     * The filter's component.
     *
     * @var string
     */
    public $component = 'select-filter';

    public $name = 'Sector';

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
        return $query->whereHas('sectors', function ($query) use ($value) {
            $query->where('sector_id', $value);
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
        if (auth()->user()->hasRole('Regional Referent')) {
            $sectors_id = [];
            foreach (auth()->user()->region->provinces as $province) {
                foreach ($province->areas as $area) {
                    $sectors_id = array_merge($sectors_id, $area->sectors->pluck('id')->toArray());
                }
            }
            $sectors = Sector::whereIn('id', $sectors_id)->orderBy('full_code')->get();
            foreach ($sectors as $item) {
                $options[$item->full_code] = $item->id;
            }
        } else {
            foreach (Sector::orderBy('full_code')->get() as $item) {
                $options[$item->full_code] = $item->id;
            }
        }

        return $options;
    }
}
