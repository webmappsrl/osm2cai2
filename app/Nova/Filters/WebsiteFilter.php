<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\BooleanFilter;
use Laravel\Nova\Http\Requests\NovaRequest;

class WebsiteFilter extends BooleanFilter
{
    public $name = 'Website';

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
        if ($value['has_website']) {
            \Log::debug('Applying has_website filter');
            return $query->whereRaw("jsonb_exists(osmfeatures_data->'properties'->'osm_tags', 'website') AND osmfeatures_data->'properties'->'osm_tags'->>'website' IS NOT NULL");
        }
        if ($value['no_website']) {
            \Log::debug('Applying no_website filter');
            return $query->whereRaw("NOT jsonb_exists(osmfeatures_data->'properties'->'osm_tags', 'website') OR osmfeatures_data->'properties'->'osm_tags'->>'website' IS NULL");
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
            'Yes' => 'has_website',
            'No' => 'no_website',
        ];
    }
}
