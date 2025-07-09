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
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(NovaRequest $request, $query, $value)
    {
        if ($value['has_website']) {
            $sql = <<<'SQL'
                    jsonb_exists(osmfeatures_data->'properties'->'osm_tags', 'website')
                    AND osmfeatures_data->'properties'->'osm_tags'->>'website' IS NOT NULL
                SQL;

            return $query->whereRaw($sql);
        }
        if ($value['no_website']) {
            $sql = <<<'SQL'
                    NOT jsonb_exists(osmfeatures_data->'properties'->'osm_tags', 'website')
                    OR osmfeatures_data->'properties'->'osm_tags'->>'website' IS NULL
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
            'Yes' => 'has_website',
            'No' => 'no_website',
        ];
    }
}
