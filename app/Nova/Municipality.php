<?php

namespace App\Nova;

use App\Nova\Filters\ScoreFilter;
use Illuminate\Http\Request;
use Laravel\Nova\Http\Requests\NovaRequest;

class Municipality extends OsmfeaturesResource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Municipality>
     */
    public static $model = \App\Models\Municipality::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The columns that should be searched.
     * Note: Search is handled by parent OsmfeaturesResource::applySearch()
     * which searches: osmfeatures_id, id, and name.
     *
     * @var array
     */
    public static $search = [];

    /**
     * Get the cards available for the request.
     */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     */
    public function filters(NovaRequest $request): array
    {
        $parentFilters = parent::filters($request);
        foreach ($parentFilters as $key => $filter) {
            if ($filter instanceof ScoreFilter) {
                unset($parentFilters[$key]);
            }
        }

        return $parentFilters;
    }

    /**
     * Get the lenses available for the resource.
     */
    public function lenses(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     */
    public function actions(NovaRequest $request): array
    {
        return [];
    }
}
