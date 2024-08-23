<?php

namespace App\Nova;

use App\Nova\Cards\LinksCard;
use App\Nova\Cards\Osm2caiStatusCard;
use App\Nova\Cards\RefCard;
use Laravel\Nova\Fields\ID;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class HikingRoute extends OsmfeaturesResource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\HikingRoute>
     */
    public static $model = \App\Models\HikingRoute::class;

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
        'osmfeatures_id'
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $osmfeaturesFields = parent::fields($request);
        //remove name field
        unset($osmfeaturesFields[1]);
        $indexFields = $this->getIndexFields();
        $detailFields = $this->getDetailFields();

        return array_merge($osmfeaturesFields, $indexFields);
    }


    /**
     * Get the cards available for the request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        // Verifica se l'ID della risorsa è presente nella richiesta
        if ($request->resourceId) {
            // Accedi al modello corrente tramite l'ID della risorsa
            $hr = \App\Models\HikingRoute::find($request->resourceId);
            $osmfeaturesData = json_decode($hr->osmfeatures_data, true);
            $linksCardData = $hr->getDataForNovaLinksCard();
            $refCardData = $osmfeaturesData['properties']['osm_tags'];
            $osm2caiStatusCardData = $osmfeaturesData['properties']['osm2cai_status'];

            return [
                (new RefCard($refCardData))->onlyOnDetail(),
                (new LinksCard($linksCardData))->onlyOnDetail(),
                (new Osm2caiStatusCard($osm2caiStatusCardData))->onlyOnDetail(),
            ];
        }


        // Restituisci un array vuoto se non sei nel dettaglio o non ci sono dati
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
        $parentFilters = parent::filters($request);
        //remove score filter
        unset($parentFilters[0]);

        return $parentFilters;
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

    private function getIndexFields()
    {
        $jsonKeys = ['osm2cai_status'];

        $specificFields = [
            Text::make('Regioni', function () {
                return 'TBI';
            })->hideFromDetail(),
            Text::make('Province', function () {
                return 'TBI';
            })->hideFromDetail(),
            Text::make('Aree', function () {
                return 'TBI';
            })->hideFromDetail(),
            Text::make('Settori', function () {
                return 'TBI';
            })->hideFromDetail(),
            Text::make('Ref', function () {
                return 'TBI';
            })->hideFromDetail(),
            Text::make('Cod_rei_osm', function () {
                return 'TBI';
            })->hideFromDetail(),
            Text::make('Cod_rei_comp', function () {
                return 'TBI';
            })->hideFromDetail(),
            Text::make('Percorribilitá', function () {
                return 'TBI';
            })->hideFromDetail(),
            Text::make('Ultima Ricognizione', function () {
                return 'TBI';
            })->hideFromDetail(),

        ];

        foreach ($jsonKeys as $key) {
            $specificFields[] = Text::make(ucfirst(str_replace('_', ' ', $key)), $key)
                ->sortable()
                ->resolveUsing(function () use ($key) {
                    return $this->{$key};
                })
                ->fillUsing(function ($request, $model, $attribute, $requestAttribute) use ($key) {
                    $debug = true;
                    $model->{$key} = $request->get($requestAttribute);
                })->hideFromDetail();
        }

        return $specificFields;
    }

    private function getDetailFields() {}
}