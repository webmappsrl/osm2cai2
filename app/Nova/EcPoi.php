<?php

namespace App\Nova;

use Laravel\Nova\Fields\Text;
use App\Helpers\Osm2caiHelper;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Http\Requests\NovaRequest;

class EcPoi extends OsmfeaturesResource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\EcPoi>
     */
    public static $model = \App\Models\EcPoi::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'name',
        'type',
        'osmfeatures_id',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return array_merge(parent::fields($request), [
            Text::make('Type', 'type')->sortable(),
            BelongsTo::make('User')->sortable()->filterable()->searchable(),
            Text::make('Score', 'score')->displayUsing(function ($value) {
                return Osm2caiHelper::getScoreAsStars($value);
            })->sortable(),
        ]);
    }
}
