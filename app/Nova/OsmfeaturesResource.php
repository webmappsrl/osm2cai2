<?php

namespace App\Nova;

use App\Helpers\Osm2caiHelper;
use App\Nova\Filters\OsmFilter;
use App\Nova\Filters\OsmtagsFilter;
use App\Nova\Filters\ScoreFilter;
use App\Nova\Filters\SourceFilter;
use App\Services\GeometryService;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Wm\MapMultiPolygon\MapMultiPolygon;
use Wm\MapPoint\MapPoint;
use Wm\Osm2caiMapMultiLinestring\Osm2caiMapMultiLinestring;
use Wm\WmPackage\Nova\AbstractEcResource;

abstract class OsmfeaturesResource extends AbstractEcResource
{
    /**
     * Get the fields displayed by the resource.
     */
    public function fields(NovaRequest $request): array
    {
        $geometryField = null;
        $model = $this->model();
        // get the table name for the model
        $tableName = $model->getTable();
        // get the geometry type of the model class
        $geometryType = GeometryService::getGeometryType($tableName, 'geometry');
        // if geometry type is point return MapPoint::make, if is multipolygon return MapMultiPolygon if is multilinestring return MapMultiLineString
        switch ($geometryType) {
            case 'Point':
                $geometryField = MapPoint::make(__('Geometry'), 'geometry')->withMeta([
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
                $geometryField = MapMultiPolygon::make(__('Geometry'), 'geometry')->withMeta([
                    'center' => ['42.795977075', '10.326813853'],
                    'attribution' => '<a href="https://webmapp.it/">Webmapp</a> contributors',
                ])->hideFromIndex();
                break;
            default:
                $centroid = $this->getCentroid();
                $geojson = $this->getGeojsonForMapView();
                $geometryField = Osm2caiMapMultiLinestring::make(__('Geometry'), 'geometry')->withMeta([
                    'center' => $centroid ?? [42, 10],
                    'attribution' => '<a href="https://webmapp.it/">Webmapp</a> contributors',
                    'tiles' => 'https://api.webmapp.it/tiles/{z}/{x}/{y}.png',
                    'defaultZoom' => 10,
                    'geojson' => json_encode($geojson),
                ])->hideFromIndex();
                break;
        }
        $fields = [
            ID::make()->sortable(),
            Text::make(__('Name'), 'name')->sortable(),
            DateTime::make(__('Created At'), 'created_at')->hideFromIndex(),
            DateTime::make(__('Updated At'), 'updated_at')->hideFromIndex(),
            Text::make(__('Osmfeatures ID'), function () {
                if (! $this->osmfeatures_id) {
                    return '';
                }

                return Osm2caiHelper::getOpenstreetmapUrlAsHtml($this->osmfeatures_id);
            })->asHtml()->hideWhenCreating()->hideWhenUpdating(),
            Text::make(__('OSM Type'), 'osmfeatures_data->properties->osm_type'),
            DateTime::make(__('Osmfeatures updated at'), 'osmfeatures_updated_at')->sortable(),
            Code::make(__('Osmfeatures Data'), 'osmfeatures_data')
                ->json()
                ->language('php')
                ->resolveUsing(function ($value) {
                    return Osm2caiHelper::getOsmfeaturesDataForNovaDetail($value);
                }),
        ];

        if ($geometryField) {
            return array_merge($fields, [$geometryField]);
        }

        return $fields;
    }

    /**
     * Get the filters available for the resource.
     */
    public function filters(NovaRequest $request): array
    {
        return [
            (new OsmFilter),
            (new ScoreFilter),
            (new OsmtagsFilter('wikimedia_commons', 'WikiMedia')),
            (new OsmtagsFilter('wikipedia', 'Wikipedia')),
            (new OsmtagsFilter('wikidata', 'Wikidata')),
            (new OsmtagsFilter('website', 'Website')),
            (new SourceFilter),
        ];
    }

    /**
     * Get the cards available for the request.
     */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     */
    public function lenses(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     */
    public function actions(NovaRequest $request): array
    {
        return [];
    }
}
