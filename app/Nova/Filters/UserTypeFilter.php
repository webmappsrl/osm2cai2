<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\BooleanFilter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Spatie\Permission\Models\Role;

class UserTypeFilter extends BooleanFilter
{
    /**
     * Apply the filter to the given query.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(NovaRequest $request, $query, $value)
    {
        if(empty($value)) {
            return $query;
        }

        if (!empty($value['Provincial Association'])||
            !empty($value['Area Association']) ||
            !empty($value['Sector Association'])
        ){
            if (!empty($value['Provincial Association'])) {
                $query->whereHas('provinces', function ($query) {
                    $query->where('provinces.id', '>', 0);
                });
                unset($value['Provincial Association']);
            }
            if (!empty($value['Area Association'])) {
                $query->whereHas('areas', function ($query) {
                    $query->where('areas.id', '>', 0);
                });
                unset($value['Area Association']);
            }
            if (!empty($value['Sector Association'])) {
                $query->whereHas('sectors', function ($query) {
                    $query->where('sectors.id', '>', 0);
                });
                unset($value['Sector Association']);
            }
        }

        $selectedRoles = array_keys(array_filter($value));
    
        return $query->whereHas('roles', function ($q) use ($selectedRoles) {
                $q->whereIn('name', $selectedRoles);
            }, '=', count($selectedRoles));
    }
    

    /**
     * Get the filter's available options.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function options(NovaRequest $request)
    {
        return [

            'Admin' => 'Administrator',
            'Referente Nazionale' => 'National Referent',
            'Referente Regionale' => 'Regional Referent',
            'Associazione Provinciale' => 'Provincial Association',
            'Associazione Area' => 'Area Association',
            'Associazione Settore' => 'Sector Association',
        ];
    }
}

