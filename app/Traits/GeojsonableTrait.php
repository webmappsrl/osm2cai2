<?php

namespace App\Traits;

use Exception;
use App\Services\GeometryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symm\Gisconverter\Gisconverter;
use Symm\Gisconverter\Exceptions\InvalidText;

trait GeojsonableTrait
{
    /**
     * Calculate the geojson of a model with only the geometry
     *
     * @return array
     */
    public function getEmptyGeojson(): ?array
    {
        $model = get_class($this);
        $obj = $model::where('id', '=', $this->id)
            ->select(
                DB::raw("ST_AsGeoJSON(geometry) as geom")
            )
            ->first();

        if (is_null($obj)) {
            return null;
        }
        $geom = $obj->geom;

        if (isset($geom)) {
            return [
                "type" => "Feature",
                "properties" => [],
                "geometry" => json_decode($geom, true)
            ];
        } else
            return null;
    }

    /**
     * Calculate the geojson of a model with only the geometry
     *
     * @return array
     */
    public function getGeojsonForMapView(): ?array
    {
        $model = get_class($this);
        $obj = $model::where('id', '=', $this->id)
            ->select(
                DB::raw("ST_AsGeoJSON(geometry) as geom")
            )
            ->first();

        if (is_null($obj)) {
            return null;
        }
        $geom = $obj->geom;

        $obj_raw_data = $model::where('id', '=', $this->id)
            ->select(
                DB::raw("ST_AsGeoJSON(geometry_raw_data) as geom_raw")
            )
            ->first();
        $geom_raw = $obj_raw_data->geom_raw;

        if (isset($geom_raw) && isset($geom)) {
            return [
                "type" => "FeatureCollection",
                "features" => [
                    0 => [
                        "type" => "Feature",
                        "properties" => [],
                        "geometry" => json_decode($geom, true),
                    ],
                    1 => [
                        "type" => "Feature",
                        "properties" => [],
                        "geometry" => json_decode($geom_raw, true),
                    ],
                ]
            ];
        }

        if (isset($geom)) {
            return [
                "type" => "Feature",
                "properties" => [],
                "geometry" => json_decode($geom, true)
            ];
        } else
            return null;
    }

    /**
     * SELECT ST_Asgeojson(ST_Centroid(geometry)) as geojson from XX where id=1;
     * geojson
     * {"type":"Point","coordinates":[11.165142884,43.974709689]}
     *
     * @return array|null
     */
    public function getCentroidGeojson(): ?array
    {
        \Log::info('Getting centroid geojson for id: ' . $this->id);

        $model = get_class($this);
        if ($this->id == null) {
            \Log::error('Id is null');
        }
        $obj = $model::where('id', '=', $this->id)
            ->select(
                DB::raw("ST_Asgeojson(ST_Centroid(geometry)) as geom")
            )
            ->first();

        if (is_null($obj)) {
            \Log::warning('No record found for id: ' . $this->id);
            return null;
        }

        $geom = $obj->geom;

        if (isset($geom)) {
            return [
                "type" => "Feature",
                "properties" => [],
                "geometry" => json_decode($geom, true)
            ];
        } else {
            \Log::warning('No geometry found for id: ' . $this->id);
            return null;
        }
    }

    /**
     * @return array|null
     */
    public function getCentroid(): ?array
    {
        $geojson = $this->getCentroidGeojson();
        if (!is_null($geojson)) {
            return $geojson['geometry']['coordinates'];
        }
        return null;
    }



    public function textToGeojson($text = '')
    {
        return  GeometryService::getService()->textToGeojson($text);
    }


    public function geojsonToGeometry($geojson)
    {
        return GeometryService::getService()->geojsonToGeometry($geojson);
    }


    /**
     * @param string json encoded geometry.
     */
    public function fileToGeometry($fileContent = '')
    {
        $geometry = null;
        $geojson = $this->textToGeojson($fileContent);
        if ($geojson)
            $geometry = $this->geojsonToGeometry($geojson);

        return $geometry;
    }

    /**
     * Return a feature collection with the related UGC features
     *
     * @return array
     */
    public function getRelatedUgcGeojson(): array
    {
        $classes = ['App\Models\UgcPoi' => 'ugc_pois', 'App\Models\UgcTrack' => 'ugc_tracks', 'App\Models\UgcMedia' => 'ugc_media'];
        $modelType = get_class($this);
        $model = $modelType::find($this->id);
        $features = [];
        $images = [];

        unset($classes[$modelType]);

        foreach ($classes as $class => $table) {
            $result = DB::select(
                'SELECT id FROM '
                    . $table
                    . ' WHERE user_id = ?'
                    . " AND ABS(EXTRACT(EPOCH FROM created_at) - EXTRACT(EPOCH FROM TIMESTAMP '"
                    . $model->created_at
                    . "')) < 5400"
                    . ' AND St_DWithin(geometry, ?, 400);',
                [
                    $model->user_id,
                    $model->geometry
                ]
            );
            foreach ($result as $row) {
                $geojson = $class::find($row->id)->getGeojson();
                if (isset($geojson))
                    $features[] = $geojson;
            }
        }

        return [
            "type" => "FeatureCollection",
            "features" => $features
        ];
    }
}
