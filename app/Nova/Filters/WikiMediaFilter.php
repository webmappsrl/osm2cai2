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
            \Log::debug('Applying has_wikimedia filter');
            return $query->whereRaw("jsonb_exists(osmfeatures_data->'properties'->'osm_tags', 'wikimedia_commons') AND osmfeatures_data->'properties'->'osm_tags'->>'wikimedia_commons' IS NOT NULL");
        }
        if ($value['no_wikimedia']) {
            \Log::debug('Applying no_wikimedia filter');
            return $query->whereRaw("NOT jsonb_exists(osmfeatures_data->'properties'->'osm_tags', 'wikimedia_commons') OR osmfeatures_data->'properties'->'osm_tags'->>'wikimedia_commons' IS NULL");
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
