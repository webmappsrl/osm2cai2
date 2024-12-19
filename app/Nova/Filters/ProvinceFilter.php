<?php

namespace App\Nova\Filters;

use App\Models\Province;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Nova\Filters\Filter;

class ProvinceFilter extends Filter
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
        $this->name = __('Province');
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
            return $query->whereHas('provinces', function ($query) use ($value) {
                $query->where('province_id', $value);
            });
        }

        return $query->where('province_id', $value);
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
        if (auth()->user()->hasRole(__('Regional Referent'))) {
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
