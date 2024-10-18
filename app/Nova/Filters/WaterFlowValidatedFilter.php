<?php

namespace App\Nova\Filters;

use App\Enums\UgcValidatedStatus;
use Illuminate\Http\Request;
use Laravel\Nova\Filters\Filter;

class WaterFlowValidatedFilter extends ValidatedFilter
{

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
        return $query->where('water_flow_rate_validated', $value);
    }
}
