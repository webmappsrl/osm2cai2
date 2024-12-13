<?php

namespace App\Services;

use SimpleXMLElement;
use App\Models\HikingRoute;
use Illuminate\Support\Facades\Http;
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
   * @return \App\Services\OsmService
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

    if (!$instance) {
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
      'reg_ref'
    ];
  }


  /**
   *
   * @param [type] $relationId
   * @return void
   */
  function hikingRouteExists($relationId)
  {
    return $this->http::head("https://www.openstreetmap.org/api/0.6/relation/" . intval($relationId))->ok();
  }

  /**
   * Return osm API data by relation id provided
   *
   * @param string|int $relationId
   * @return array
   */
  function getHikingRoute($relationId)
  {
    $return = false;
    $response = $this->http::get("https://www.openstreetmap.org/api/0.6/relation/" . intval($relationId));
    if ($response->ok()) {
      $allowedKeys = $this->getRelationApiFieldsKey();
      $xml = $response->body();
      $relation = (new SimpleXMLElement($xml))->relation;
      foreach ($relation->tag as $tag) {

        $key = str_replace(':', '_', (string) $tag['k']);
        if (in_array($key, $allowedKeys)) {
          $return[$key . '_osm'] = (string) $tag['v'];
          $return[$key] = (string) $tag['v'];
        }
      }
      $return['relation_id'] = $relationId;
    }
    return $return;
  }

  function getHikingRouteGeojson($relationId)
  {
    $return = false;
    $response = $this->http::get("https://hiking.waymarkedtrails.org/api/v1/details/relation/" . intval($relationId) . "/geometry/geojson");
    if ($response->ok()) {
      $return = $response->body();
    }

    return $return;
  }

  function getHikingRouteGpx($relationId)
  {
    $return = false;
    $response = $this->http::get("https://hiking.waymarkedtrails.org/api/v1/details/relation/" . intval($relationId) . "/geometry/gpx");
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
  function getHikingRouteGeometry($relationId)
  {

    //todo
    $gpx = $this->getHikingRouteGpx($relationId);
    if ($gpx) {
      $service = GeometryService::getService();
      $geojson = $service->textToGeojson($gpx);
      $geometry = $service->geojsonToMultilinestringGeometry($geojson);
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
  function getHikingRouteGeometry3857($relationId)
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
    $relationId = $model->relation_id;
    if (is_null($osmHr))
      $osmHr = $this->getHikingRoute($relationId);
    //non è i tempo reale
    $osmGeo = $this->getHikingRouteGeometry($relationId);
    //AGGIORNO GEOMETRIA
    $model->geometry = $osmGeo;
    $model->geometry_osm = $osmGeo;
    foreach ($this->getRelationApiFieldsKey() as $attribute) {
      $key = $attribute;
      $key_osm = $attribute . '_osm';
      if (isset($osmHr[$key]))
        $model->$key = $osmHr[$key];
      else
        $model->$key = null;
      if (isset($osmHr[$key_osm]))
        $model->$key_osm = $osmHr[$key_osm];
      else
        $model->$key_osm = null;
    }
    $model->setGeometrySync();
    $model->setRefREIComp();
    $model->setOsm2CaiStatus();
    $model->save();
    $model->computeAndSetTechInfo();
    //rifattorizzazione dei settori
    $model->computeAndSetSectors();
    return $model->save();
  }
}
