<?php

namespace App\Nova\Filters;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\User;
use App\Models\HikingRoute;
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
        $this->name = __('Area');
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

        // Gestione del caso "senza area"
        if ($value === 'no_area') {
            if ($model instanceof HikingRoute || $model instanceof User) {
                return $query->whereDoesntHave('areas');
            }

            return $query->whereNull('area_id');
        }

        if ($model instanceof HikingRoute) {
            return $query->whereHas('areas', function ($query) use ($value) {
                $query->where('area_id', $value);
            });
        }

        if ($model instanceof User) {
            return $query->whereHas('areas', function ($query) use ($value) {
                $query->where('area_id', $value);
            });
        }

        return $query->where('area_id', $value);
    }

    /**
     * Get the filter's available options.
     *
     * @return array
     */
    public function options(Request $request)
    {
        $options = [];
        
        // Aggiungi l'opzione "Senza area" come prima opzione
        $options[__('Senza area')] = 'no_area';

        if (auth()->user()->hasRole(UserRole::RegionalReferent)) {
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
