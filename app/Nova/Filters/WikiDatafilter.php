<?php

namespace App\Nova\Filters;

use Laravel\Nova\Filters\BooleanFilter;
use Laravel\Nova\Http\Requests\NovaRequest;

class WikiDatafilter extends BooleanFilter
{
    public $name = 'WikiData';

    /**
     * Apply the filter to the given query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  mixed  $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(NovaRequest $request, $query, $value)
    {
        if ($value['has_wikidata']) {
            $sql = <<<'SQL'
                    jsonb_exists(osmfeatures_data->'properties'->'osm_tags', 'wikidata')
                    AND osmfeatures_data->'properties'->'osm_tags'->>'wikidata' IS NOT NULL
                SQL;

            return $query->whereRaw($sql);
        }
        if ($value['no_wikidata']) {
            $sql = <<<'SQL'
                    NOT jsonb_exists(osmfeatures_data->'properties'->'osm_tags', 'wikidata')
                    OR osmfeatures_data->'properties'->'osm_tags'->>'wikidata' IS NULL
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
            'Yes' => 'has_wikidata',
            'No' => 'no_wikidata',
        ];
    }
}
