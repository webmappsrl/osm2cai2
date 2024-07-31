<?php

namespace App\Nova;

use Laravel\Nova\Resource;
use Wm\MapPoint\MapPoint;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Text;
use App\Helpers\Osm2caiHelper;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Http\Requests\NovaRequest;
use App\Nova\Filters\ScoreFilter;
use App\Nova\Filters\SourceFilter;
use App\Nova\Filters\WebsiteFilter;
use App\Nova\Filters\WikiDataFilter;
use App\Nova\Filters\WikiMediaFilter;
use App\Nova\Filters\WikiPediaFilter;

abstract class OsmfeaturesResource extends Resource
{
    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),
            Text::make('Name', 'name')->sortable(),
            DateTime::make('Created At', 'created_at')->hideFromIndex(),
            DateTime::make('Updated At', 'updated_at')->hideFromIndex(),
            Text::make('Score', 'score')->displayUsing(function ($value) {
                return Osm2caiHelper::getScoreAsStars($value);
            })->sortable(),
            MapPoint::make('geometry')->withMeta([
                'center' => [42, 10],
                'attribution' => '<a href="https://webmapp.it/">Webmapp</a> contributors',
                'tiles' => 'https://api.webmapp.it/tiles/{z}/{x}/{y}.png',
                'minZoom' => 8,
                'maxZoom' => 17,
                'defaultZoom' => 13,
                'defaultCenter' => [42, 10],
            ])->onlyOnDetail(),
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
     * Get the filters available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [
            (new ScoreFilter),
            (new WikiPediaFilter),
            (new WikiDataFilter),
            (new WikiMediaFilter),
            (new WebsiteFilter),
            (new SourceFilter),
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
