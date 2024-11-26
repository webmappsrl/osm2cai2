<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Imumz\Nova4FieldMap\Nova4FieldMap;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

class MountainGroups extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\MountainGroups>
     */
    public static $model = \App\Models\MountainGroups::class;

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
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function fields(Request $request)
    {
        $aggregatedData = json_decode($this->aggregated_data);
        $intersectings = json_decode($this->intersectings, true);

        return [
            ID::make(__('ID'), 'id')->sortable(),
            Text::make('Nome', 'name')->sortable(),
            Textarea::make('Descrizione', 'description')->hideFromIndex(),
            Nova4FieldMap::make('Mappa')
                ->type('GeoJson')
                ->geoJson(json_encode($this->getEmptyGeojson()))
                ->zoom(9)
                ->onlyOnDetail(),
            Text::make('POI Generico', function () use ($aggregatedData) {
                return $aggregatedData->ec_pois_count;
            })->sortable(),
            Text::make('POI Rifugio', function () use ($aggregatedData) {
                return $aggregatedData->cai_huts_count;
            })->sortable(),
            Text::make('Percorsi POI Totali', function () use ($aggregatedData) {
                return $aggregatedData->poi_total;
            })->sortable(),
            Text::make('Attivitá o Esperienze', function () use ($aggregatedData) {
                return $aggregatedData->sections_count;
            })->sortable(),
            // Text::make('Rifugi Intersecanti', function () use ($intersectings) {
            //     return count($intersectings['huts']);
            // })->sortable(),
            // Text::make('POI Intersecanti', function () use ($intersectings) {
            //     return count($intersectings['ec_pois']);
            // })->sortable(),
            // Text::make('Sezioni Intersecanti', function () use ($intersectings) {
            //     return count($intersectings['sections']);
            // })->sortable(),
            // Text::make('Percorsi Intersecanti', function () use ($intersectings) {
            //     return count($intersectings['hiking_routes']);
            // })->sortable(),
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
