<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Symm\Gisconverter\Gisconverter;
use Symm\Gisconverter\Exceptions\InvalidText;

class GeometryService
{

  /**
   * Return an istance of this class
   *
   * @return \App\Services\GeometryService
   */
  public static function getService()
  {
    return app(__CLASS__);
  }


  public function geojsonToGeometry($geojson)
  {
    return DB::select(DB::raw("select (ST_Force3D(ST_GeomFromGeoJSON('" . $geojson . "'))) as g "))[0]->g;
  }

  /**
   * Convert geojson in MULTILINESTRING postgis geometry with correct SRID
   *
   * @param string $geojson
   * @return string - the postgis geometry in string format
   */
  public function geojsonToMultilinestringGeometry($geojson)
  {
    return DB::select(DB::raw("select (
        ST_Multi(
          ST_GeomFromGeoJSON('" . $geojson . "')
        )
    ) as g "))[0]->g;
  }

  /**
   * Convert geojson in MULTILINESTRING postgis geometry with 3857 SRID
   *
   * @param string $geojson
   * @return string - the postgis geometry in string format
   */
  public function geojsonToMultilinestringGeometry3857($geojson)
  {
    return DB::select(DB::raw("select (
        ST_Multi(
          ST_Transform( ST_GeomFromGeoJSON('" . $geojson . "' ) , 3857 )
        )
    ) as g "))[0]->g;
  }

  public function geometryTo4326Srid($geometry)
  {
    return DB::select(DB::raw("select (
      ST_Transform('" . $geometry . "', 4326)
    ) as g "))[0]->g;
  }


  public function textToGeojson($text)
  {
    $geometry = $contentType = null;
    if ($text) {
      if (strpos($text, '<?xml') !== false && strpos($text, '<?xml') < 10) {
        $geojson = '';
        if ('' === $geojson) {
          try {
            $geojson = Gisconverter::gpxToGeojson($text);
            $content = json_decode($geojson);
            $contentType = @$content->type;
          } catch (InvalidText $ec) {
          }
        }

        if ('' === $geojson) {
          try {
            $geojson = Gisconverter::kmlToGeojson($text);
            $content = json_decode($geojson);
            $contentType = @$content->type;
          } catch (InvalidText $ec) {
          }
        }
      } else {
        $content = json_decode($text);
        $isJson = json_last_error() === JSON_ERROR_NONE;
        if ($isJson) {
          $contentType = $content->type;
        }
      }

      if ($contentType) {
        switch ($contentType) {
          case "GeometryCollection":
            foreach ($content->geometries as $item) {
              if ($item->type == 'LineString') {
                $contentGeometry = $item;
              }
            }
            break;
          case "FeatureCollection":
            $contentGeometry = $content->features[0]->geometry;
            break;
          case "LineString":
            $contentGeometry = $content;
            break;
          default:
            $contentGeometry = $content->geometry;
            break;
        }

        $geometry = json_encode($contentGeometry);
      }

      return $geometry;
    }
  }
}
