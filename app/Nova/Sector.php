<?php

namespace App\Nova;

use Laravel\Nova\Fields\ID;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Number;
use Imumz\Nova4FieldMap\Nova4FieldMap;
use Laravel\Nova\Http\Requests\NovaRequest;

class Sector extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Sector>
     */
    public static $model = \App\Models\Sector::class;

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
        'name',
        'human_name',
        'code',
        'full_code'
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            Text::make(__('Codice'), 'name')->sortable()->hideWhenUpdating()->required(),
            Text::make(__('Name'), 'human_name')
                ->sortable()
                ->help('Modifica il nome del settore')->required()
                ->rules('max:254'),
            Text::make(__('Code'), 'code')->sortable()->required()->rules('max:1'),
            Text::make(__('Responsabili'), 'manager')->hideFromIndex(),
            Number::make(__('Numero Atteso'), 'num_expected')->required(),
            Text::make(__('Full code'), 'full_code')->readonly(),
            File::make('Geometry')->store(function (Request $request, $model) {
                return $model->fileToGeometry($request->geometry->get());
            })->onlyOnForms()->hideWhenUpdating()->required(),
            Nova4FieldMap::make('Mappa')
                ->type('GeoJson')
                ->geoJson(json_encode($this->getEmptyGeojson())),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }
}