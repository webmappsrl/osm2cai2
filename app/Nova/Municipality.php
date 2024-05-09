<?php

namespace App\Nova;

use App\Helpers\Osm2caiHelper;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;
use Wm\MapMultiPolygon\MapMultiPolygon;

class Municipality extends Resource
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
     *
     * @var array
     */
    public static $search = [
        'id', 'name',
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
            ID::make()->sortable(),
            Text::make('Name', 'name')->sortable(),
            DateTime::make('Created At', 'created_at')->hideFromIndex(),
            DateTime::make('Updated At', 'updated_at')->hideFromIndex(),
            MapMultiPolygon::make('Geometry')->withMeta([
                'center' => ['42.795977075', '10.326813853'],
                'attribution' => '<a href="https://webmapp.it/">Webmapp</a> contributors',
            ])->hideFromIndex(),
            Text::make('Osmfeatures ID', function () {
                return Osm2caiHelper::getOpenstreetmapUrlAsHtml($this->osmfeatures_id);
            })->asHtml(),
            DateTime::make('Osmfeatures updated at', 'osmfeatures_updated_at')->sortable(),
            Code::make('Osmfeatures Data', 'osmfeatures_data')
                ->json()
                ->language('php')
                ->resolveUsing(function ($value) {
                    return  Osm2caiHelper::getOsmfeaturesDataForNovaDetail($value);
                }),

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
