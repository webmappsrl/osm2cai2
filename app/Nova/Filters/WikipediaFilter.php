<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\BooleanFilter;
use Laravel\Nova\Http\Requests\NovaRequest;

class WikipediaFilter extends BooleanFilter
{
    public $name = 'Wikipedia';

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
            $sql = <<<'SQL'
            jsonb_exists(osmfeatures_data->'properties'->'osm_tags', 'wikipedia')
            AND osmfeatures_data->'properties'->'osm_tags'->>'wikipedia' IS NOT NULL
        SQL;

            return $query->whereRaw($sql);
        }
        if ($value['no_wikipedia']) {
            $sql = <<<'SQL'
            NOT jsonb_exists(osmfeatures_data->'properties'->'osm_tags', 'wikipedia')
            OR osmfeatures_data->'properties'->'osm_tags'->>'wikipedia' IS NULL
        SQL;

            return $query->whereRaw($sql);
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
