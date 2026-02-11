<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class MissingOnOsmfeaturesFilter extends Filter
{
    /**
     * The filter's component.
     *
     * @var string
     */
    public $component = 'select-filter';

    /**
     * The displayable name of the filter.
     *
     * @var string
     */
    public $name = 'Presente su OSMFeatures';

    /**
     * Apply the filter to the given query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(NovaRequest $request, $query, $value)
    {
        // Gestisce sia valori booleani che stringhe (per compatibilità)
        if ($value === false || $value === 'false' || $value === 'missing' || $value === 0) {
            // Mostra solo i pali che NON esistono più su osmfeatures
            return $query->where('osmfeatures_exists', false);
        } elseif ($value === true || $value === 'true' || $value === 'exists' || $value === 1) {
            // Mostra solo i pali che esistono su osmfeatures
            return $query->where(function ($q) {
                $q->where('osmfeatures_exists', true)
                    ->orWhereNull('osmfeatures_exists');
            });
        }

        return $query;
    }

    /**
     * Get the filter's available options.
     *
     * @return array
     */
    public function options(NovaRequest $request)
    {
        return [
            __('Present') => true,
            __('Not present (deleted from OSMFEATURES)') => false,
        ];
    }
}
