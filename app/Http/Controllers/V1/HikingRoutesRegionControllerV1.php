<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\HikingRoute;
use App\Models\Region;
use Exception;
use GeoJson\Geometry\LineString;
use Illuminate\Support\Facades\DB;

class HikingRoutesRegionControllerV1 extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/v1/hiking-routes/region/{region_code}/{sda}",
     *      tags={"Api V1"},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Returns all the hiking routes OSM2CAI IDs based on the given region code and SDA number.
     *                       These ids can be used in the geojson API hiking-route",
     *
     *       @OA\MediaType(
     *             mediaType="application/json",
     *
     *             @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="id",
     *                     description="Internal osm2cai Identifier",
     *                     type="integer"
     *                 ),
     *                 example={1269,652,273,}
     *             )
     *         )
     *      ),
     *
     *     @OA\Parameter(
     *         name="region_code",
     *         in="path",
     *         description="
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="varchar",
     *         )
     *     ),
     *
     *      @OA\Parameter(
     *         name="sda",
     *         in="path",
     *         description="SDA (stato di accatastamento) (e.g. 3 or 3,1 or 0,1,2). SDA=3 means ready to be validated, SDA=4 means validated by CAI expert",
     *         required=true,
     *
     *         @OA\Schema(
     *             type="string",
     *             format="varchar"
     *         )
     *     ),
     * )
     */
    public function hikingroutelist(string $region_code, string $sda)
    {
        $region_code = strtoupper($region_code);

        $sda = explode(',', $sda);

        // Check if region exists
        $region = Region::where('code', $region_code)->first();
        if (! $region) {
            return response(['error' => 'Region not found with code '.$region_code], 404);
        }

        // Get hiking routes for region and status
        $list = HikingRoute::whereHas('regions', function ($query) use ($region_code) {
            $query->where('code', $region_code);
        })->whereIn('osm2cai_status', $sda)->get();

        if ($list->isEmpty()) {
            return response(['error' => 'No hiking routes found for region '.$region_code.' and SDA '.implode(',', $sda)], 404);
        }

        $list = $list->pluck('id')->toArray();

        // Return
        return response($list, 200, ['Content-type' => 'application/json']);
    }

    /**
     * @OA\Get(
     *      path="/api/v1/hiking-routes-osm/region/{region_code}/{sda}",
     *      tags={"Api V1"},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Returns all the hiking routes OSM IDs based on the given region code and SDA number.
     *                       OSMID can be used in hiking-route-osm API or directly in OpenStreetMap relation by the following URL:
     *                       https://openstreetmap.org/relation/{OSMID}. Remember that the data on OSM can be differente from data in
     *                       OSM2CAI after validation.",
     *
     *      @OA\MediaType(
     *             mediaType="application/json",
     *
     *             @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="OSM",
     *                     description="Open Streen Map identification",
     *                     type="integer"
     *                 ),
     *                 example={7766787,3151885,2736729}
     *             )
     *         )
     *      ),
     *
     *     @OA\Parameter(
     *         name="region_code",
     *         in="path",
     *         description="
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="varchar",
     *         )
     *     ),
     *
     *      @OA\Parameter(
     *         name="sda",
     *         in="path",
     *         description="SDA (stato di accatastamento) (e.g. 3 or 3,1 or 0,1,2). SDA=3 means ready to be validated, SDA=4 means validated by CAI expert",
     *         required=true,
     *
     *         @OA\Schema(
     *             type="string",
     *             format="varchar"
     *         )
     *     ),
     * )
     */
    public function hikingrouteosmlist(string $region_code, string $sda)
    {
        $region_code = strtoupper($region_code);

        $sda = explode(',', $sda);
        $list = HikingRoute::query();
        $list = HikingRoute::whereHas('regions', function ($query) use ($region_code) {
            $query->where('code', $region_code);
        })->whereIn('osm2cai_status', $sda)->get();

        $list = $list->pluck('osmfeatures_data.properties.osm_id')->toArray();

        // Return
        return response($list, 200, ['Content-type' => 'application/json']);
    }

    /**
     * @OA\Get(
     *      path="/api/v1/hiking-route/{id}",
     *      tags={"Api V1"},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Returns the geojson of a Hiking Route based on the given OSM2CAI ID.",
     *
     *      @OA\MediaType(
     *             mediaType="application/json",
     *
     *             @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="type",
     *                     description="Geojson type",
     *                     type="string"
     *                 ),
     *                  @OA\Property(
     *                     property="properties",
     *                     type="object",
     *                     @OA\Property( property="id", type="integer",  description="OSM2CAI ID"),
     *                     @OA\Property( property="relation_ID", type="integer",  description="OSMID"),
     *                     @OA\Property( property="source", type="string",  description="from SDA=3 and over must be survey:CAI or other values accepted by CAI as valid source"),
     *                     @OA\Property( property="cai_scale", type="string",  description="CAI scale difficulty: T E EE"),
     *                     @OA\Property( property="from", type="string",  description="start point"),
     *                     @OA\Property( property="to", type="string",  description="end point"),
     *                     @OA\Property( property="ref", type="string",  description="local ref hiking route number must be three number and a letter only in last position for variants"),
     *                      @OA\Property( property="public_page", type="string",  description="public url for the hiking route"),
     *                     @OA\Property( property="sda", type="integer",  description="stato di accatastamento"),
     *                     @OA\Property( property="validation_date", type="date", description="date of validation of the hiking route, visible only for sda = 4 format YYYY-mm-dd")
     *                 ),
     *                 @OA\Property(property="geometry", type="object",
     *                      @OA\Property( property="type", type="string",  description="Postgis geometry types: LineString, MultiLineString"),
     *                      @OA\Property( property="coordinates", type="object",  description="hiking routes coordinates (WGS84)")
     *                 ),
     *                 example={"type":"Feature","properties":{"id":2421,"relation_id":4179533,"source":
     * "survey:CAI","cai_scale":"E","from":"Castellare","to":"Campo di Croce","ref":"117","public_page":"https://osm2cai.cai.it/hiking-route/id/2421","sda":4,"validation_date":"2022-07-29T00:00:00.000000Z"},"geometry":
     * {"type":"MultiLineString","coordinates":{{{10.4495294,43.7615252},{10.4495998,43.7615566}}}}}
     *             )
     *         )
     *      ),
     *
     *      @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="The OSM2CAI ID of a specific Hiking Route (e.g. 2421)",
     *         required=true,
     *
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     * )
     */
    public function hikingroutebyid(int $id)
    {
        try {
            $item = HikingRoute::find($id);
            if (! $item) {
                return response('No Hiking Route found with this id', 404, ['Content-type' => 'application/json']);
            }
            $HR = $this->createGeoJSONFromModel($item);
        } catch (Exception $e) {
            return response('No Hiking Route found with this id', 404, ['Content-type' => 'application/json']);
        }

        // Return
        return response($HR, 200, ['Content-type' => 'application/json']);
    }

    /**
     * @OA\Get(
     *      path="/api/v1/hiking-route-osm/{id}",
     *      tags={"Api V1"},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Returns the geojson of a Hiking Route based on the given OSM2CAI ID.",
     *
     *      @OA\MediaType(
     *             mediaType="application/json",
     *
     *             @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="type",
     *                     description="Geojson type",
     *                     type="string"
     *                 ),
     *                  @OA\Property(
     *                     property="properties",
     *                     type="object",
     *                     @OA\Property( property="id", type="integer",  description="OSM2CAI ID"),
     *                     @OA\Property( property="relation_ID", type="integer",  description="OSMID"),
     *                     @OA\Property( property="source", type="string",  description="from SDA=3 and over must be survey:CAI or other values accepted by CAI as valid source"),
     *                     @OA\Property( property="cai_scale", type="string",  description="CAI scale difficulty: T E EE"),
     *                     @OA\Property( property="from", type="string",  description="start point"),
     *                     @OA\Property( property="to", type="string",  description="end point"),
     *                     @OA\Property( property="ref", type="string",  description="local ref hiking route number must be three number and a letter only in last position for variants"),
     *                     @OA\Property( property="public_page", type="string",  description="public url for the hiking route"),
     *                     @OA\Property( property="sda", type="integer",  description="stato di accatastamento"),
     *                     @OA\Property( property="validation_date", type="date",  description="date of validation of the hiking route, visible only for sda = 4 format YYYY-mm-dd")
     *
     *                 ),
     *                 @OA\Property(property="geometry", type="object",
     *                      @OA\Property( property="type", type="string",  description="Postgis geometry types: LineString, MultiLineString"),
     *                      @OA\Property( property="coordinates", type="object",  description="hiking routes coordinates (WGS84)")
     *                 ),
     *                 example={"type":"Feature","properties":{"id":2421,"relation_id":4179533,"source":
     * "survey:CAI","cai_scale":"E","from":"Castellare","to":"Campo di Croce","ref":"117","public_page":"https://osm2cai.cai.it/hiking-route/id/2421","sda":4,"validation_date":"2022-07-29T00:00:00.000000Z"},"geometry":
     * {"type":"MultiLineString","coordinates":{{{10.4495294,43.7615252},{10.4495998,43.7615566}}}}}
     *             )
     *         )
     *      ),
     *
     *      @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="The OSM relation ID of a specific Hiking Route (e.g. 13442719)",
     *         required=true,
     *
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     * )
     */
    public function hikingroutebyosmid(int $id)
    {
        try {
            $item = HikingRoute::whereRaw("(osmfeatures_data->'properties'->>'osm_id')::integer = ?", [$id])->first();

            if (! $item) {
                return response('No Hiking Route found with this OSMid', 404, ['Content-type' => 'application/json']);
            }

            $HR = $this->createGeoJSONFromModel($item);

            if (! $HR) {
                return response('Error creating GeoJSON', 500, ['Content-type' => 'application/json']);
            }

            return response($HR, 200, ['Content-type' => 'application/json']);
        } catch (Exception $e) {
            return response('No Hiking Route found with this OSMid', 404, ['Content-type' => 'application/json']);
        }
    }

    /**
     * @OA\Get(
     *      path="/api/v1/hiking-routes/bb/{bounding_box}/{sda}",
     *      tags={"Api V1"},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Returns all the hiking routes OSM2CAI IDs based on the given bounding box coordinates( xmin,ymin,xmax,ymax)  and SDA number.
     *                       These ids can be used in the geojson API hiking-route",
     *
     *       @OA\MediaType(
     *             mediaType="application/json",
     *
     *             @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="id",
     *                     description="Internal osm2cai Identifier",
     *                     type="integer"
     *                 ),
     *                 example={1269,652,273,}
     *             )
     *         )
     *      ),
     *
     *     @OA\Parameter(
     *         name="bounding_box",
     *         in="path",
     *         description="List of WGS84 lat,lon cordinates in this order(xmin,ymin,xmax,ymax)",
     *         required=true,
     *
     *         @OA\Schema(
     *             type="string",
     *             format="varchar",
     *         )
     *     ),
     *
     *      @OA\Parameter(
     *         name="sda",
     *         in="path",
     *         description="SDA (stato di accatastamento) (e.g. 3 or 3,1 or 0,1,2). SDA=3 means ready to be validated, SDA=4 means validated by CAI expert",
     *         required=true,
     *
     *         @OA\Schema(
     *             type="string",
     *             format="varchar"
     *         )
     *     ),
     * )
     */
    public function hikingroutelist_bb(string $bb, string $sda)
    {
        try {
            $coordinates = explode(',', $bb);

            $list = DB::table('hiking_routes')
                ->select('id')
                ->whereRaw('ST_Intersects(geometry, ST_MakeEnvelope(?, ?, ?, ?, 4326))', [
                    floatval($coordinates[0]),
                    floatval($coordinates[1]),
                    floatval($coordinates[2]),
                    floatval($coordinates[3]),
                ])
                ->whereIn('osm2cai_status', explode(',', $sda))
                ->pluck('id')
                ->toArray();

            return response($list, 200, ['Content-type' => 'application/json']);
        } catch (Exception $e) {
            return response(['error' => 'Error processing bounding box query: '.$e->getMessage()], 500, ['Content-type' => 'application/json']);
        }
    }

    /**
     * @OA\Get(
     *      path="/api/v1/hiking-routes-osm/bb/{bounding_box}/{sda}",
     *      tags={"Api V1"},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Returns all the hiking routes OSM IDs based on the given bounding box coordinates( xmin,ymin,xmax,ymax)  and SDA number.
     *                       These ids can be used in the geojson API hiking-route",
     *
     *       @OA\MediaType(
     *             mediaType="application/json",
     *
     *             @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="id",
     *                     description="OSM Identifier",
     *                     type="integer"
     *                 ),
     *                example={7766787,3151885,2736729}
     *             )
     *         )
     *      ),
     *
     *     @OA\Parameter(
     *         name="bounding_box",
     *         in="path",
     *         description="List of WGS84 lat,lon cordinates in this order(xmin,ymin,xmax,ymax)",
     *         required=true,
     *
     *         @OA\Schema(
     *             type="string",
     *             format="varchar",
     *         )
     *     ),
     *
     *      @OA\Parameter(
     *         name="sda",
     *         in="path",
     *         description="SDA (stato di accatastamento) (e.g. 3 or 3,1 or 0,1,2). SDA=3 means ready to be validated, SDA=4 means validated by CAI expert",
     *         required=true,
     *
     *         @OA\Schema(
     *             type="string",
     *             format="varchar"
     *         )
     *     ),
     * )
     */
    public function hikingrouteosmlist_bb(string $bb, string $sda)
    {
        try {
            $coordinates = explode(',', $bb);

            $list = DB::table('hiking_routes')
                ->selectRaw("(osmfeatures_data->'properties'->>'osm_id')::integer as osm_id")
                ->whereRaw('ST_Intersects(geometry, ST_MakeEnvelope(?, ?, ?, ?, 4326))', [
                    floatval($coordinates[0]),
                    floatval($coordinates[1]),
                    floatval($coordinates[2]),
                    floatval($coordinates[3]),
                ])
                ->whereIn('osm2cai_status', explode(',', $sda))
                ->pluck('osm_id')
                ->toArray();

            return response($list, 200, ['Content-type' => 'application/json']);
        } catch (Exception $e) {
            return response(['error' => 'Error processing bounding box query: '.$e->getMessage()], 500, ['Content-type' => 'application/json']);
        }
    }

    /**
     * @OA\Get(
     *      path="/api/v1/hiking-routes-collection/bb/{bounding_box}/{sda}",
     *      tags={"Api V1"},
     *
     *      @OA\Response(
     *          response=200,
     *          description="Returns all the feautures collection based on the given bounding box coordinates( xmin,ymin,xmax,ymax)  and SDA number.",
     *
     *       @OA\MediaType(
     *             mediaType="application/json",
     *
     *             @OA\Schema(
     *
     *                 @OA\Property(
     *                     property="collection",
     *                     description="Feature Collection",
     *                     type="json"
     *                 ),
     *                 example={"type":"Feature","properties":{"id":2421,"relation_id":4179533,"source":
     * "survey:CAI","cai_scale":"E","from":"Castellare","to":"Campo di Croce","ref":"117","public_page":"https://osm2cai.cai.it/hiking-route/id/2421","sda":4,"validation_date":"2022-07-29T00:00:00.000000Z"},"geometry":
     * {"type":"MultiLineString","coordinates":{{{10.4495294,43.7615252},{10.4495998,43.7615566}}}}},
     *             )
     *         )
     *      ),
     *
     *     @OA\Parameter(
     *         name="bounding_box",
     *         in="path",
     *         description="List of WGS84 lat,lon cordinates in this order(xmin,ymin,xmax,ymax)",
     *         required=true,
     *
     *         @OA\Schema(
     *             type="string",
     *             format="varchar",
     *         )
     *     ),
     *
     *      @OA\Parameter(
     *         name="sda",
     *         in="path",
     *         description="SDA (stato di accatastamento) (e.g. 0,1,2,3,4). SDA=3 means ready to be validated, SDA=4 means validated by CAI expert",
     *         required=true,
     *
     *         @OA\Schema(
     *             type="string",
     *             format="varchar"
     *         )
     *     ),
     * )
     */
    public function hikingroutelist_collection(string $bb, string $sda)
    {
        try {
            $boundingBox = explode(',', $bb);
            $area = $this->getAreaBoundingBox(
                floatval($boundingBox[0]),
                floatval($boundingBox[1]),
                floatval($boundingBox[2]),
                floatval($boundingBox[3])
            );

            if ($area > 0.1) {
                return response(['error' => 'Bounding box is too large'], 500, ['Content-type' => 'application/json']);
            }

            return $this->geojsonByBoundingBox(
                $sda,
                floatval($boundingBox[0]),
                floatval($boundingBox[1]),
                floatval($boundingBox[2]),
                floatval($boundingBox[3])
            );
        } catch (Exception $e) {
            return response(['error' => 'Error processing bounding box query: '.$e->getMessage()], 500, ['Content-type' => 'application/json']);
        }
    }

    public function getAreaBoundingBox($la0, $lo0, $la1, $lo1)
    {
        $res = DB::select('SELECT ST_area(ST_makeenvelope(?, ?, ?, ?, 4326))', [$la0, $lo0, $la1, $lo1]);

        return floatval($res[0]->st_area);
    }

    public function createGeoJSONFromModel($item)
    {
        $obj = HikingRoute::where('id', '=', $item->id)
            ->select(
                DB::raw('ST_AsGeoJSON(geometry) as geom')
            )
            ->first();

        if (is_null($obj)) {
            return null;
        }

        $geom = $obj->geom;
        $osmfeaturesDataProperties = $item->osmfeatures_data['properties'] ?? null;

        if (isset($geom)) {
            $response = [
                'type' => 'Feature',
                'properties' => [
                    'id' => $item->id,
                    'relation_id' => $osmfeaturesDataProperties['osm_id'] ?? null,
                    'source' => $osmfeaturesDataProperties['source'] ?? null,
                    'cai_scale' => $osmfeaturesDataProperties['cai_scale'] ?? null,
                    'from' => $osmfeaturesDataProperties['from'] ?? null,
                    'to' => $osmfeaturesDataProperties['to'] ?? null,
                    'ref' => $osmfeaturesDataProperties['ref'] ?? null,
                    'public_page' => url('/hiking-route/id/'.$item->id),
                    'sda' => $item->osm2cai_status ?? $osmfeaturesDataProperties['osm2cai_status'] ?? null,
                    'issues_status' => $item->issues_status ?? '',
                    'issues_description' => $item->issues_description ?? '',
                    'issues_last_update' => $item->issues_last_update ?? '',
                ],
                'geometry' => json_decode($geom, true),
            ];
        }

        return $response;
    }

    public function geojsonByBoundingBox($osm2cai_status, $lo0, $la0, $lo1, $la1): string
    {
        try {
            $features = DB::table('hiking_routes')
                ->whereRaw('ST_Intersects(geometry, ST_MakeEnvelope(?, ?, ?, ?, 4326))', [
                    floatval($lo0),
                    floatval($la0),
                    floatval($lo1),
                    floatval($la1),
                ])
                ->whereIn('osm2cai_status', explode(',', $osm2cai_status))
                ->get([
                    'id',
                    'osm2cai_status',
                    'validation_date',
                    'osmfeatures_data',
                    DB::raw('ST_AsGeoJSON(geometry) as geom'),
                ]);

            if ($features->isEmpty()) {
                return json_encode([
                    'type' => 'FeatureCollection',
                    'features' => [],
                ]);
            }

            $featureCollection = [
                'type' => 'FeatureCollection',
                'features' => $features->map(function ($item) {
                    $osmProperties = $item->osmfeatures_data['properties'] ?? [];

                    return [
                        'type' => 'Feature',
                        'properties' => [
                            'id' => $item->id,
                            'relation_id' => $osmProperties['osm_id'] ?? null,
                            'source' => $osmProperties['source'] ?? null,
                            'cai_scale' => $osmProperties['cai_scale'] ?? null,
                            'from' => $osmProperties['from'] ?? null,
                            'to' => $osmProperties['to'] ?? null,
                            'ref' => $osmProperties['ref'] ?? null,
                            'public_page' => url('/hiking-route/id/'.$item->id),
                            'sda' => $item->osm2cai_status,
                            'validation_date' => $item->validation_date,
                            'network' => $osmProperties['network'] ?? null,
                            'osmc_symbol' => $osmProperties['osmc_symbol'] ?? null,
                            'roundtrip' => $item->tdh['roundtrip'] ?? null,
                            'symbol' => $osmProperties['symbol'] ?? null,
                            'description' => $osmProperties['description'] ?? null,
                            'website' => $osmProperties['website'] ?? null,
                        ],
                        'geometry' => json_decode($item->geom),
                    ];
                })->all(),
            ];

            return json_encode($featureCollection);
        } catch (Exception $e) {
            throw new Exception('Error creating GeoJSON collection: '.$e->getMessage());
        }
    }
}
