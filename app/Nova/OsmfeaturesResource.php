<?php

namespace App\Nova;

use App\Helpers\Osm2caiHelper;
use App\Nova\Filters\OsmFilter;
use App\Nova\Filters\ScoreFilter;
use App\Nova\Filters\SourceFilter;
use App\Nova\Filters\WebsiteFilter;
use App\Nova\Filters\WikiDataFilter;
use App\Nova\Filters\WikiMediaFilter;
use App\Nova\Filters\WikiPediaFilter;
use App\Services\GeometryService;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;
use Wm\MapMultiLinestring\MapMultiLinestring;
use Wm\MapMultiPolygon\MapMultiPolygon;
use Wm\MapPoint\MapPoint;

abstract class OsmfeaturesResource extends Resource
{
    /**
     * Get the fields displayed by the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $geometryField = null;
        $model = $this->model();
        //get the table name for the model
        $tableName = $model->getTable();
        //get the geometry type of the model class
        $geometryType = GeometryService::getGeometryType($tableName, 'geometry');
        //if geometry type is point return MapPoint::make, if is multipolygon return MapMultiPolygon if is multilinestring return MapMultiLineString
        switch ($geometryType) {
            case 'Point':
                $geometryField = MapPoint::make('geometry')->withMeta([
                    'center' => [42, 10],
                    'attribution' => '<a href="https://webmapp.it/">Webmapp</a> contributors',
                    'tiles' => 'https://api.webmapp.it/tiles/{z}/{x}/{y}.png',
                    'minZoom' => 8,
                    'maxZoom' => 17,
                    'defaultZoom' => 13,
                    'defaultCenter' => [42, 10],
                ])->hideFromIndex();
                break;
            case 'MultiPolygon':
                $geometryField = MapMultiPolygon::make('Geometry')->withMeta([
                    'center' => ['42.795977075', '10.326813853'],
                    'attribution' => '<a href="https://webmapp.it/">Webmapp</a> contributors',
                ])->hideFromIndex();
                break;
            default:
                $geometryField = MapMultiLinestring::make('geometry')->withMeta([
                    'center' => [42, 10],
                    'attribution' => '<a href="https://webmapp.it/">Webmapp</a> contributors',
                    'tiles' => 'https://api.webmapp.it/tiles/{z}/{x}/{y}.png',
                    'minZoom' => 5,
                    'maxZoom' => 17,
                    'defaultZoom' => 10,
                    'graphhopper_api' => 'https://graphhopper.webmapp.it/route',
                    'graphhopper_profile' => 'hike',
                ])->hideFromIndex();
                break;
        }
        $fields = [
            ID::make()->sortable(),
            Text::make('Name', 'name')->sortable(),
            DateTime::make('Created At', 'created_at')->hideFromIndex(),
            DateTime::make('Updated At', 'updated_at')->hideFromIndex(),
            Text::make('Osmfeatures ID', function () {
                if (! $this->osmfeatures_id) {
                    return '';
                }

                return Osm2caiHelper::getOpenstreetmapUrlAsHtml($this->osmfeatures_id);
            })->asHtml()->hideWhenCreating()->hideWhenUpdating(),
            Text::make('OSM Type', 'osmfeatures_data->properties->osm_type'),
            DateTime::make('Osmfeatures updated at', 'osmfeatures_updated_at')->sortable(),
            Code::make('Osmfeatures Data', 'osmfeatures_data')
                ->json()
                ->language('php')
                ->resolveUsing(function ($value) {
                    return  Osm2caiHelper::getOsmfeaturesDataForNovaDetail($value);
                }),
        ];

        if ($geometryField) {
            return array_merge($fields, [$geometryField]);
        }

        return $fields;
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [
            (new OsmFilter),
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
     * @param  NovaRequest  $request
     * @return array
     */
    public function cards(NovaRequest $request)
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
