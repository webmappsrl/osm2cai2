<?php

namespace App\Nova\Filters;

use App\Enums\UserRole;
use App\Models\Province;
use App\Models\User;
use App\Models\HikingRoute;
use Illuminate\Http\Request;
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
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(Request $request, $query, $value)
    {
        $model = $query->getModel();

        // Gestione del caso "senza provincia"
        if ($value === 'no_province') {
            if ($model instanceof HikingRoute || $model instanceof User) {
                return $query->whereDoesntHave('provinces');
            }

            return $query->whereNull('province_id');
        }

        if ($model instanceof HikingRoute) {
            return $query->whereHas('provinces', function ($query) use ($value) {
                $query->where('province_id', $value);
            });
        }

        if ($model instanceof User) {
            return $query->whereHas('provinces', function ($query) use ($value) {
                $query->where('name', Province::find($value)->name);
            });
        }

        return $query->where('province_id', $value);
    }

    /**
     * Get the filter's available options.
     *
     * @return array
     */
    public function options(Request $request)
    {
        $options = [];
        
        // Aggiungi l'opzione "Senza provincia" come prima opzione
        $options[__('Senza provincia')] = 'no_province';

        if (auth()->user()->hasRole(UserRole::RegionalReferent)) {
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
