<?php

namespace App\Nova;

use Wm\MapPoint\MapPoint;
use Laravel\Nova\Fields\ID;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Text;
use App\Helpers\Osm2caiHelper;
use App\Nova\Filters\ScoreFilter;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Http\Requests\NovaRequest;
use App\Nova\Filters\SourceFilter;
use App\Nova\Filters\WebsiteFilter;
use App\Nova\Filters\WikiDataFilter;
use App\Nova\Filters\WikiMediaFilter;
use App\Nova\Filters\WikiPediaFilter;

class Poles extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Poles>
     */
    public static $model = \App\Models\Poles::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'ref';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id', 'ref', 'osmfeatures_id',
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
            ID::make()->sortable(),
            Text::make('Name', 'name')->sortable(),
            Text::make('Ref', 'ref')->sortable(),
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
