<?php

namespace App\Nova\Filters;

use App\Enums\UserRole;
use Laravel\Nova\Filters\BooleanFilter;
use Laravel\Nova\Http\Requests\NovaRequest;

const PROVINCIAL_ASSOCIATION = 'Provincial Association';
const AREA_ASSOCIATION = 'Area Association';
const SECTOR_ASSOCIATION = 'Sector Association';

class UserTypeFilter extends BooleanFilter
{
    /**
     * Apply the filter to the given query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(NovaRequest $request, $query, $value)
    {
        if (empty($value)) {
            return $query;
        }

        if (! empty($value[PROVINCIAL_ASSOCIATION]) ||
            ! empty($value[AREA_ASSOCIATION]) ||
            ! empty($value[SECTOR_ASSOCIATION])
        ) {
            if (! empty($value[PROVINCIAL_ASSOCIATION])) {
                $query->whereHas('provinces', function ($query) {
                    $query->where('provinces.id', '>', 0);
                });
                unset($value[PROVINCIAL_ASSOCIATION]);
            }
            if (! empty($value[AREA_ASSOCIATION])) {
                $query->whereHas('areas', function ($query) {
                    $query->where('areas.id', '>', 0);
                });
                unset($value[AREA_ASSOCIATION]);
            }
            if (! empty($value[SECTOR_ASSOCIATION])) {
                $query->whereHas('sectors', function ($query) {
                    $query->where('sectors.id', '>', 0);
                });
                unset($value[SECTOR_ASSOCIATION]);
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
     * @return array
     */
    public function options(NovaRequest $request)
    {
        return [

            'Admin' => UserRole::Administrator->value,
            'Referente Nazionale' => UserRole::NationalReferent->value,
            'Referente Regionale' => UserRole::RegionalReferent->value,
            'Associazione Provinciale' => PROVINCIAL_ASSOCIATION,
            'Associazione Area' => AREA_ASSOCIATION,
            'Associazione Settore' => SECTOR_ASSOCIATION,
        ];
    }
}
