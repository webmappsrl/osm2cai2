<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\BooleanFilter;
use Laravel\Nova\Http\Requests\NovaRequest;

class WikiDataFilter extends BooleanFilter
{
    public $name = 'WikiData';

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
        if ($value['has_wikidata']) {
            \Log::debug('Applying has_wikidata filter');
            return $query->whereRaw("jsonb_exists(osmfeatures_data->'properties'->'osm_tags', 'wikidata') AND osmfeatures_data->'properties'->'osm_tags'->>'wikidata' IS NOT NULL");
        }
        if ($value['no_wikidata']) {
            \Log::debug('Applying no_wikidata filter');
            return $query->whereRaw("NOT jsonb_exists(osmfeatures_data->'properties'->'osm_tags', 'wikidata') OR osmfeatures_data->'properties'->'osm_tags'->>'wikidata' IS NULL");
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
            'Yes' => 'has_wikidata',
            'No' => 'no_wikidata',
        ];
    }
}
