<?php

namespace App\Nova\Filters;

use Illuminate\Support\Facades\DB;
use Laravel\Nova\Filters\BooleanFilter;
use Laravel\Nova\Http\Requests\NovaRequest;

class WikipediaFilter extends BooleanFilter
{
    public $name = 'WikiPedia';

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
        if ($value['has_wikipedia']) {
            return $query->whereRaw("jsonb_exists(cast(osmfeatures_data->'properties'->'osm_tags' as jsonb), 'wikipedia')");
        }
        if ($value['no_wikipedia']) {
            return $query->whereRaw("NOT jsonb_exists(cast(osmfeatures_data->'properties'->'osm_tags' as jsonb), 'wikipedia')");
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
            'Yes' => 'has_wikipedia',
            'No' => 'no_wikipedia',
        ];
    }
}
