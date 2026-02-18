<?php

namespace App\Nova\Filters;

use App\Enums\UserRole;
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

    public function __construct()
    {
        $this->name = __('Sector');
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
        // Gestione del caso "senza settore"
        if ($value === 'no_sector') {
            return $query->whereDoesntHave('sectors');
        }

        // Gestione normale con settore specifico
        return $query->whereHas('sectors', function ($query) use ($value) {
            $query->where('sector_id', $value);
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
        
        // Aggiungi l'opzione "Senza settore" come prima opzione
        $options[__('Senza settore')] = 'no_sector';
        
        if (auth()->user()->hasRole(UserRole::RegionalReferent)) {
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
