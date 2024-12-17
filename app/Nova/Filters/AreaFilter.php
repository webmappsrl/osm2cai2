<?php

namespace App\Nova\Filters;

use App\Models\Area;
use Illuminate\Http\Request;
use Laravel\Nova\Filters\Filter;

class AreaFilter extends Filter
{
    /**
     * The filter's component.
     *
     * @var string
     */
    public $component = 'select-filter';

    public $name;

    public function __construct()
    {
        $this->name = 'Area';
    }

    /**
     * Apply the filter to the given query.
     *
     * @param Request $request
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param mixed $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(Request $request, $query, $value)
    {
        if ($query->getModel() instanceof \App\Models\HikingRoute) {
            return $query->whereHas('area', function ($query) use ($value) {
                $query->where('area_id', $value);
            });
        }

        return $query->where('area_id', $value);
    }

    /**
     * Get the filter's available options.
     *
     * @param Request $request
     * @return array
     */
    public function options(Request $request)
    {
        $options = [];
        if (auth()->user()->hasRole('Regional Referent')) {
            $areas = Area::whereIn('province_id', auth()->user()->region->provinces->pluck('id')->toArray())->orderBy('name')->get();
            foreach ($areas as $item) {
                $options[$item->name] = $item->id;
            }
        } else {
            foreach (Area::orderBy('name')->get() as $item) {
                $options[$item->name] = $item->id;
            }
        }

        return $options;
    }
}
