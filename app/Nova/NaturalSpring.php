<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Wm\MapPoint\MapPoint;

class NaturalSpring extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\NaturalSpring>
     */
    public static $model = \App\Models\NaturalSpring::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @return string
     */
    public function title()
    {
        return $this->name;
    }

    public static function label()
    {
        return __('Database Acqua Sorgente');
    }

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'name',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function fields(Request $request)
    {
        return [
            ID::make(__('ID'), 'id')->sortable(),
            Text::make(__('Code'), 'code')->hideFromIndex(),
            Text::make(__('Name'), 'name')->sortable(),
            Text::make(__('Region'), 'region')->sortable(),
            Text::make(__('Province'), 'province')->sortable(),
            Text::make(__('Municipality'), 'municipality')->sortable(),
            Text::make(__('Source'), 'source')->hideFromIndex(),
            Text::make(__('Source Reference'), 'source_ref')->hideFromIndex(),
            Text::make(__('Source Code'), 'source_code')->hideFromIndex(),
            Text::make(__('Reference'), 'loc_ref')->hideFromIndex(),
            Text::make(__('Operator'), 'operator')->hideFromIndex(),
            Text::make(__('Type'), 'type')->hideFromIndex(),
            Text::make(__('Volume'), 'volume')->hideFromIndex(),
            Text::make(__('Mass Flow Rate'), 'mass_flow_rate')->hideFromIndex(),
            Text::make(__('Temperature'), 'temperature')->hideFromIndex(),
            Text::make(__('Conductivity'), 'conductivity')->hideFromIndex(),
            Text::make(__('Survey Date'), 'survey_date')->hideFromIndex(),
            Text::make(__('Latitude'), 'lat')->hideFromIndex(),
            Text::make(__('Longitude'), 'lon')->hideFromIndex(),
            Text::make(__('Elevation'), 'elevation')->hideFromIndex(),
            Text::make(__('Note'), 'note')->hideFromIndex(),
            MapPoint::make('geometry')->withMeta([
                'center' => [42, 10],
                'attribution' => '<a href="https://webmapp.it/">Webmapp</a> contributors',
                'tiles' => 'https://api.webmapp.it/tiles/{z}/{x}/{y}.png',
                'minZoom' => 8,
                'maxZoom' => 17,
                'defaultZoom' => 13,
            ])->hideFromIndex(),

        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }

    public function authorizeToView(Request $request)
    {
        return auth()->user()->isValidatorForFormId('water');
    }

    public function authorizeToViewAny(Request $request)
    {
        return auth()->user()->isValidatorForFormId('water');
    }

    public static function availableForNavigation(Request $request)
    {
        return auth()->user()->isValidatorForFormId('water');
    }
}
