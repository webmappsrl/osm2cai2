<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Wm\MapMultiPolygon\MapMultiPolygon;

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
        'full_code',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  NovaRequest  $request
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
            MapMultiPolygon::make('Geometry')->withMeta([
                'center' => ['42.795977075', '10.326813853'],
                'attribution' => '<a href="https://webmapp.it/">Webmapp</a> contributors',
            ])->hideFromIndex(),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }
}
