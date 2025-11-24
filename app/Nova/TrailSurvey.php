<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Textarea;

class TrailSurvey extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\TrailSurvey>
     */
    public static $model = \App\Models\TrailSurvey::class;

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
        'description',
    ];

    /**
     * Get the resource label
     */
    public static function label()
    {
        return __('Trail Surveys');
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @return array
     */
    public function fields(Request $request)
    {
        return [
            ID::make()->sortable(),

            BelongsTo::make(__('Hiking Route'), 'hikingRoute', HikingRoute::class)
                ->searchable()
                ->required(),

            BelongsTo::make(__('Owner'), 'owner', User::class)
                ->searchable()
                ->required(),

            Date::make(__('Start Date'), 'start_date')
                ->required()
                ->sortable(),

            Date::make(__('End Date'), 'end_date')
                ->required()
                ->sortable(),

            Textarea::make(__('Description'), 'description')
                ->nullable()
                ->rows(3),

            BelongsToMany::make(__('Ugc Pois'), 'ugcPois', UgcPoi::class)
                ->searchable(),

            BelongsToMany::make(__('Ugc Tracks'), 'ugcTracks', UgcTrack::class)
                ->searchable(),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @return array
     */
    public function cards(Request $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array
     */
    public function filters(Request $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array
     */
    public function lenses(Request $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array
     */
    public function actions(Request $request)
    {
        return [];
    }
}

