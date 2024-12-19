<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Symm\Gisconverter\Exceptions\InvalidText;
use Symm\Gisconverter\Gisconverter;

class GeometryService
{
    /**
     * Return an istance of this class
     *
     * @return GeometryService
     */
    public static function getService()
    {
        return app(__CLASS__);
    }

    public function geojsonToGeometry($geojson)
    {
        if (is_array($geojson)) {
            $geojson = json_encode($geojson);
        }

        return DB::select("select (ST_Force3D(ST_GeomFromGeoJSON('".$geojson."'))) as g ")[0]->g;
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
          ST_GeomFromGeoJSON('".$geojson."')
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
          ST_Transform( ST_GeomFromGeoJSON('".$geojson."' ) , 3857 )
        )
    ) as g "))[0]->g;
    }

    public function geometryTo4326Srid($geometry)
    {
        return DB::select(DB::raw("select (
      ST_Transform('".$geometry."', 4326)
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
                $content = json_decode($text, true);
                $isJson = json_last_error() === JSON_ERROR_NONE;
                if ($isJson) {
                    $contentType = $content['type'];
                }
            }

            if ($contentType) {
                switch ($contentType) {
                    case 'GeometryCollection':
                        foreach ($content['geometries'] as $item) {
                            if ($item['type'] == 'LineString') {
                                $contentGeometry = $item;
                            }
                        }
                        break;
                    case 'FeatureCollection':
                        $contentGeometry = $content['features'][0]['geometry'];
                        break;
                    case 'LineString':
                        $contentGeometry = $content;
                        break;
                    default:
                        $contentGeometry = $content['geometry'];
                        break;
                }
            }

            return $contentGeometry;
        }
    }

    /**
     * Get the geometry type of the given model.
     *
     * @param string $table The name of the table.
     * @param string $geometryColumn The name of the geometry column.
     * @return string
     */
    public static function getGeometryType(string $table, string $geometryColumn)
    {
        // Costruire la query per determinare il tipo di geometria
        if ($table == 'hiking_routes') {
            $query = <<<SQL
        SELECT 
            ST_GeometryType(
                CASE 
                    WHEN GeometryType({$geometryColumn}) = 'GEOMETRY' THEN {$geometryColumn}
                    ELSE {$geometryColumn}
                END
            ) AS geom_type
        FROM {$table}
        LIMIT 1;
        SQL;
        } else {
            $query = <<<SQL
        SELECT 
            ST_GeometryType({$geometryColumn}) AS geom_type
        FROM {$table}
        LIMIT 1;
        SQL;
        }

        // Eseguire la query e ottenere il tipo di geometria
        $type = DB::selectOne($query);

        // Restituire il tipo di geometria senza il prefisso "ST_"
        return $type ? str_replace('ST_', '', $type->geom_type) : 'Unknown';
    }

    public function getCentroid($geometry)
    {
        $geometry = $this->geojsonToGeometry($geometry);

        return DB::select("select ST_AsGeoJSON(ST_Centroid('".$geometry."')) as g")[0]->g;
    }
}
