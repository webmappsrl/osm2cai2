<?php

namespace App\Services;

use App\Models\HikingRoute;
use App\Models\Sector;
use App\Nova\Actions\CalculateIntersections;
use App\Services\GeometryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;
use Symfony\Component\String\Exception\RuntimeException;

class OsmService
{
    protected $http;

    public function __construct()
    {
        $this->http = Http::class;
    }

    /**
     * Return an istance of this class
     *
     * @return OsmService
     */
    public static function getService()
    {
        return app(__CLASS__);
    }

    /**
     * Handle dynamic, static calls to the object.
     *
     * @param  string  $method
     * @param  array  $args
     * @return mixed
     *
     * @throws \RuntimeException
     */
    public static function __callStatic($method, $args)
    {
        $instance = static::getService();

        if (! $instance) {
            throw new RuntimeException('OsmService has not been set.');
        }

        return $instance->$method(...$args);
    }

    /**
     * SERVICES METHODS
     */
    public function getRelationApiFieldsKey()
    {
        return  [
            'ref',
            'old_ref',
            'source_ref',
            'survey_date',
            'name',
            'rwn_name',
            'ref_REI',
            'from',
            'to',
            'osmc_symbol',
            'network',
            'roundtrip',
            'symbol',
            'symbol_it',
            'ascent',
            'descent',
            'distance',
            'duration_forward',
            'duration_backward',
            'operator',
            'state',
            'description',
            'description_it',
            'website',
            'wikimedia_commons',
            'maintenance',
            'maintenance_it',
            'note',
            'note_it',
            'note_project_page',
            'cai_scale',
            'network',
            'source',
            'ele_from',
            'ele_to',
            'ele_max',
            'ele_min',
            'reg_ref',
        ];
    }

    /**
     * @param [type] $relationId
     * @return void
     */
    public function hikingRouteExists($relationId)
    {
        return $this->http::head('https://www.openstreetmap.org/api/0.6/relation/'.intval($relationId))->ok();
    }

    /**
     * Return osm API data by relation id provided
     *
     * @param string|int $relationId
     * @return array
     */
    public function getHikingRoute($relationId)
    {
        $return = false;
        $response = $this->http::get('https://www.openstreetmap.org/api/0.6/relation/'.intval($relationId));
        if ($response->ok()) {
            $allowedKeys = $this->getRelationApiFieldsKey();
            $xml = $response->body();
            $relation = (new SimpleXMLElement($xml))->relation;
            foreach ($relation->tag as $tag) {
                $key = str_replace(':', '_', (string) $tag['k']);
                if (in_array($key, $allowedKeys)) {
                    $return[$key] = (string) $tag['v'];
                }
            }
            $return['osm_id'] = $relationId;
        }

        return $return;
    }

    public function getHikingRouteGeojson($relationId)
    {
        $return = false;
        $response = $this->http::get('https://hiking.waymarkedtrails.org/api/v1/details/relation/'.intval($relationId).'/geometry/geojson');
        if ($response->ok()) {
            $return = $response->body();
        }

        return $return;
    }

    public function getHikingRouteGpx($relationId)
    {
        $return = false;
        $response = $this->http::get('https://hiking.waymarkedtrails.org/api/v1/details/relation/'.intval($relationId).'/geometry/gpx');
        if ($response->ok()) {
            $return = $response->body();
        }

        return $return;
    }

    /**
     * Get route gpx geometry by relation id
     * convert gpx in geojson
     * convert geojson in MULTILINESTRING postgis geometry with correct SRID
     *
     * @param string|int $relationId
     * @return string|false geometry on success, false otherwise
     */
    public function getHikingRouteGeometry($relationId)
    {
        //todo
        $gpx = $this->getHikingRouteGpx($relationId);
        if ($gpx) {
            $service = GeometryService::getService();
            $geojson = $service->textToGeojson($gpx);
            $geometry = $service->geojsonToMultilinestringGeometry(json_encode($geojson));

            return $geometry;
        }

        return false;
    }

    /**
     * Get route gpx geometry by relation id
     * convert gpx in geojson
     * convert geojson in MULTILINESTRING postgis geometry with OSM SRID (3857)
     *
     * @param string|int $relationId
     * @return string|false geometry on success, false otherwise
     */
    public function getHikingRouteGeometry3857($relationId)
    {
        //todo
        $gpx = $this->getHikingRouteGpx($relationId);
        if ($gpx) {
            $service = GeometryService::getService();
            $geojson = $service->textToGeojson($gpx);
            $geometry = $service->geojsonToMultilinestringGeometry3857($geojson);

            return $geometry;
        }

        return false;
    }

    public function updateHikingRouteModelWithOsmData(HikingRoute $model, $osmHr = null)
    {
        $osmfeaturesData = $model->osmfeatures_data;
        $relationId = $osmfeaturesData['properties']['osm_id'];
        if (is_null($osmHr)) {
            $osmHr = $this->getHikingRoute($relationId);
        }

        $osmGeo = $this->getHikingRouteGeometry($relationId);
        $model->geometry = $osmGeo;
        $osmfeaturesData['geometry'] = json_decode(DB::select("SELECT ST_AsGeoJSON('".$osmGeo."') as geojson")[0]->geojson, true);
        foreach ($this->getRelationApiFieldsKey() as $key) {
            if (isset($osmHr[$key])) {
                $osmfeaturesData['properties'][$key] = $osmHr[$key];
            } else {
                $osmfeaturesData['properties'][$key] = null;
            }
        }
        $model->osmfeatures_data = $osmfeaturesData;
        $model->save();

        $sectorsIntersecting = $model->getIntersections(new Sector());

        //associate sectors intersecting with the route
        $model->sectors()->sync($sectorsIntersecting->pluck('id'));

        return $model->save();
    }
}
