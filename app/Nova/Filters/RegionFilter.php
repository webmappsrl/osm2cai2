<?php

namespace App\Nova\Filters;

use App\Models\Club;
use App\Models\Province;
use App\Models\Region;
use App\Models\Sector;
use App\Models\User;
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

        // Gestione del caso "senza regione"
        if ($value === 'no_region') {
            if ($model instanceof Province || $model instanceof Club || $model instanceof User) {
                return $query->whereNull('region_id');
            }

            if ($model instanceof Sector) {
                return $query->whereHas('area.province', function ($query) {
                    $query->whereNull('region_id');
                });
            }

            return $query->whereDoesntHave('regions');
        }

        // Gestione normale con regione specifica
        if ($model instanceof Province || $model instanceof Club) {
            return $query->where('region_id', $value);
        }

        if ($model instanceof Sector) {
            return $query->whereHas('area.province', function ($query) use ($value) {
                $query->where('region_id', $value);
            });
        }

        if ($model instanceof User) {
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

        // Aggiungi l'opzione "Senza regione" come prima opzione
        $options[__('Senza regione')] = 'no_region';

        // Aggiungi tutte le regioni
        foreach (Region::all() as $region) {
            $options[$region->name] = $region->id;
        }

        return $options;
    }
}
