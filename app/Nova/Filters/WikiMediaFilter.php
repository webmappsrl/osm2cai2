<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\BooleanFilter;
use Laravel\Nova\Http\Requests\NovaRequest;

class WikiMediaFilter extends BooleanFilter
{
    public $name = 'WikiMedia';

    /**
     * Apply the filter to the given query.
     *
     * @param  NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(NovaRequest $request, $query, $value)
    {
        if ($value['has_wikimedia']) {
            return $query->whereRaw("jsonb_exists(cast(osmfeatures_data->'properties'->'osm_tags' as jsonb), 'wikimedia_commons')");
        }
        if ($value['no_wikimedia']) {
            return $query->whereRaw("NOT jsonb_exists(cast(osmfeatures_data->'properties'->'osm_tags' as jsonb), 'wikimedia_commons')");
        }

        return $query;
    }

    /**
     * Get the filter's available options.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function options(NovaRequest $request)
    {
        return [
            'Yes' => 'has_wikimedia',
            'No' => 'no_wikimedia',
        ];
    }
}
