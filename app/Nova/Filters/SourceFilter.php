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
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(NovaRequest $request, $query, $value)
    {
        if (! $value) {
            return $query;
        }

        if ($value['has_source']) {
            $sql = <<<'SQL'
            jsonb_exists(osmfeatures_data->'properties'->'osm_tags', 'source') 
            AND osmfeatures_data->'properties'->'osm_tags'->>'source' IS NOT NULL
        SQL;

            return $query->whereRaw($sql);
        }

        if ($value['no_source']) {
            $sql = <<<'SQL'
            NOT jsonb_exists(osmfeatures_data->'properties'->'osm_tags', 'source') 
            OR osmfeatures_data->'properties'->'osm_tags'->>'source' IS NULL
        SQL;

            return $query->whereRaw($sql);
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
            'Yes' => 'has_source',
            'No' => 'no_source',
        ];
    }
}
