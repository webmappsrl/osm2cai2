<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\BooleanFilter;
use Laravel\Nova\Http\Requests\NovaRequest;

class SourceFilter extends BooleanFilter
{
    public $name = 'Source';

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
        if ($value['has_source']) {
            \Log::debug('Applying has_source filter');
            return $query->whereRaw("jsonb_exists(osmfeatures_data->'properties'->'osm_tags', 'source') AND osmfeatures_data->'properties'->'osm_tags'->>'source' IS NOT NULL");
        }
        if ($value['no_source']) {
            \Log::debug('Applying no_source filter');
            return $query->whereRaw("NOT jsonb_exists(osmfeatures_data->'properties'->'osm_tags', 'source') OR osmfeatures_data->'properties'->'osm_tags'->>'source' IS NULL");
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
            'Yes' => 'has_source',
            'No' => 'no_source',
        ];
    }
}
