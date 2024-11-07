<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\HikingRoute;
use App\Models\Region;
use Exception;
use GeoJson\Geometry\LineString;
use GeoJson\Geometry\Polygon;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

define('_BOUNDIG_BOX_LIMIT', 0.1);

class HikingRoutesRegionControllerV1 extends Controller
{


    /**
     * 
     * @OA\Get(
     *      path="/api/v1/hiking-routes/region/{region_code}/{sda}",
     *      tags={"Api V1"},
     *      @OA\Response(
     *          response=200,
     *          description="Returns all the hiking routes OSM2CAI IDs based on the given region code and SDA number. 
     *                       These ids can be used in the geojson API hiking-route",
     *       @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="id",
     *                     description="Internal osm2cai Identifier",
     *                     type="integer"
     *                 ),
     *                 example={1269,652,273,}
     *             )
     *         )     
     *      ),
     *     @OA\Parameter(
     *         name="region_code",
     *         in="path",
     *         description="
Regione code according to CAI convention: <br/>
<br />A -> Friuli Venezia Giulia
<br />B -> Veneto
<br />C -> Trentino Alto Adige
<br />D -> Lombardia
<br />E -> Piemonte
<br />F -> Val d'Aosta
<br />G -> Liguria
<br />H -> Emilia Romagna
<br />L -> Toscana
<br />M -> Marche
<br />N -> Umbria
<br />O -> Lazio
<br />P -> Abruzzo
<br />Q -> Molise
<br />S -> Campania
<br />R -> Puglia
<br />T -> Basilicata
<br />U -> Calabria
<br />V -> Sicilia
<br />Z -> Sardegna",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="varchar",
     *         )
     *     ),
     *      @OA\Parameter(
     *         name="sda",
     *         in="path",
     *         description="SDA (stato di accatastamento) (e.g. 3 or 3,1 or 0,1,2). SDA=3 means ready to be validated, SDA=4 means validated by CAI expert",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="varchar"
     *         )
     *     ),
     * )
     * 
     */
    public function hikingroutelist(string $region_code, string $sda)
    {
        $region_code = strtoupper($region_code);

        $sda = explode(',', $sda);
        $list = HikingRoute::query();
        $list = HikingRoute::whereHas('regions', function ($query) use ($region_code) {
            $query->where('code', $region_code);
        })->whereIn('osm2cai_status', $sda)->get();

        $list = $list->pluck('id')->toArray();

        // Return
        return response($list, 200, ['Content-type' => 'application/json']);
    }

    /**
     * 
     * @OA\Get(
     *      path="/api/v1/hiking-routes-osm/region/{region_code}/{sda}",
     *      tags={"Api V1"},
     *      @OA\Response(
     *          response=200,
     *          description="Returns all the hiking routes OSM IDs based on the given region code and SDA number.
     *                       OSMID can be used in hiking-route-osm API or directly in OpenStreetMap relation by the following URL:
     *                       https://openstreetmap.org/relation/{OSMID}. Remember that the data on OSM can be differente from data in 
     *                       OSM2CAI after validation.",
     *      @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="OSM",
     *                     description="Open Streen Map identification",
     *                     type="integer"
     *                 ),
     *                 example={7766787,3151885,2736729}
     *             )
     *         )     
     *      ),
     *     @OA\Parameter(
     *         name="region_code",
     *         in="path",
     *         description="
Regione code according to CAI convention: <br/>
<br />A -> Friuli Venezia Giulia
<br />B -> Veneto
<br />C -> Trentino Alto Adige
<br />D -> Lombardia
<br />E -> Piemonte
<br />F -> Val d'Aosta
<br />G -> Liguria
<br />H -> Emilia Romagna
<br />L -> Toscana
<br />M -> Marche
<br />N -> Umbria
<br />O -> Lazio
<br />P -> Abruzzo
<br />Q -> Molise
<br />S -> Campania
<br />R -> Puglia
<br />T -> Basilicata
<br />U -> Calabria
<br />V -> Sicilia
<br />Z -> Sardegna",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="varchar",
     *         )
     *     ),
     *      @OA\Parameter(
     *         name="sda",
     *         in="path",
     *         description="SDA (stato di accatastamento) (e.g. 3 or 3,1 or 0,1,2). SDA=3 means ready to be validated, SDA=4 means validated by CAI expert",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="varchar"
     *         )
     *     ),
     * )
     * 
     */
    public function hikingrouteosmlist(string $region_code, string $sda)
    {
        $region_code = strtoupper($region_code);

        $sda = explode(',', $sda);
        $list = HikingRoute::query();
        $list = HikingRoute::whereHas('regions', function ($query) use ($region_code) {
            $query->where('code', $region_code);
        })->whereIn('osm2cai_status', $sda)->get();

        $list = $list->pluck('relation_id')->toArray();

        // Return
        return response($list, 200, ['Content-type' => 'application/json']);
    }

    /**
     * 
     * @OA\Get(
     *      path="/api/v1/hiking-route/{id}",
     *      tags={"Api V1"},
     *      @OA\Response(
     *          response=200,
     *          description="Returns the geojson of a Hiking Route based on the given OSM2CAI ID.",
     *      @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
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
     *      @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="The OSM2CAI ID of a specific Hiking Route (e.g. 2421)",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     * )
     * 
     */
    public function hikingroutebyid(int $id)
    {

        try {
            $item = HikingRoute::find($id);
            $HR = $this->createGeoJSONFromModel($item);
        } catch (Exception $e) {
            return response('No Hiking Route found with this id', 404, ['Content-type' => 'application/json']);
        }


        // Return
        return response($HR, 200, ['Content-type' => 'application/json']);
    }

    /**
     * 
     * @OA\Get(
     *      path="/api/v1/hiking-route-osm/{id}",
     *      tags={"Api V1"},
     *      @OA\Response(
     *          response=200,
     *          description="Returns the geojson of a Hiking Route based on the given OSM2CAI ID.",
     *      @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
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
     *      @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="The OSM relation ID of a specific Hiking Route (e.g. 13442719)",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     * )
     * 
     */
    public function hikingroutebyosmid(int $id)
    {

        try {
            $item = HikingRoute::where('relation_id', $id)->get();
            $HR = $this->createGeoJSONFromModel($item[0]);
        } catch (Exception $e) {
            return response('No Hiking Route found with this OSMid', 404, ['Content-type' => 'application/json']);
        }


        // Return
        return response($HR, 200, ['Content-type' => 'application/json']);
    }


    /**
     *
     * @OA\Get(
     *      path="/api/v1/hiking-routes/bb/{bounding_box}/{sda}",
     *      tags={"Api V1"},
     *      @OA\Response(
     *          response=200,
     *          description="Returns all the hiking routes OSM2CAI IDs based on the given bounding box coordinates( xmin,ymin,xmax,ymax)  and SDA number.
     *                       These ids can be used in the geojson API hiking-route",
     *       @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="id",
     *                     description="Internal osm2cai Identifier",
     *                     type="integer"
     *                 ),
     *                 example={1269,652,273,}
     *             )
     *         )
     *      ),
     *     @OA\Parameter(
     *         name="bounding_box",
     *         in="path",
     *         description="List of WGS84 lat,lon cordinates in this order(xmin,ymin,xmax,ymax)",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="varchar",
     *         )
     *     ),
     *      @OA\Parameter(
     *         name="sda",
     *         in="path",
     *         description="SDA (stato di accatastamento) (e.g. 3 or 3,1 or 0,1,2). SDA=3 means ready to be validated, SDA=4 means validated by CAI expert",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="varchar"
     *         )
     *     ),
     * )
     *
     */
    public function hikingroutelist_bb(string $bb, string $sda)
    {
        $coordinates = explode(',', $bb);
        $list = DB::table('hiking_routes')
            ->select('id')
            ->whereRaw("ST_within(geometry,ST_MakeEnvelope(" . $bb . ", 4326))")
            ->whereIn('osm2cai_status', explode(',', $sda))
            ->pluck('id')->toArray();
        return response($list, 200, ['Content-type' => 'application/json']);
    }

    /**
     *
     * @OA\Get(
     *      path="/api/v1/hiking-routes-osm/bb/{bounding_box}/{sda}",
     *      tags={"Api V1"},
     *      @OA\Response(
     *          response=200,
     *          description="Returns all the hiking routes OSM IDs based on the given bounding box coordinates( xmin,ymin,xmax,ymax)  and SDA number.
     *                       These ids can be used in the geojson API hiking-route",
     *       @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="id",
     *                     description="OSM Identifier",
     *                     type="integer"
     *                 ),
     *                example={7766787,3151885,2736729}
     *             )
     *         )
     *      ),
     *     @OA\Parameter(
     *         name="bounding_box",
     *         in="path",
     *         description="List of WGS84 lat,lon cordinates in this order(xmin,ymin,xmax,ymax)",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="varchar",
     *         )
     *     ),
     *      @OA\Parameter(
     *         name="sda",
     *         in="path",
     *         description="SDA (stato di accatastamento) (e.g. 3 or 3,1 or 0,1,2). SDA=3 means ready to be validated, SDA=4 means validated by CAI expert",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="varchar"
     *         )
     *     ),
     * )
     *
     */
    public function hikingrouteosmlist_bb(string $bb, string $sda)
    {
        $coordinates = explode(',', $bb);
        $list = DB::table('hiking_routes')
            ->select('relation_id')
            ->whereRaw("ST_within(geometry,ST_MakeEnvelope(" . $bb . ", 4326))")
            ->whereIn('osm2cai_status', explode(',', $sda))
            ->pluck('relation_id')->toArray();
        return response($list, 200, ['Content-type' => 'application/json']);
    }

    /**
     *
     * @OA\Get(
     *      path="/api/v1/hiking-routes-collection/bb/{bounding_box}/{sda}",
     *      tags={"Api V1"},
     *      @OA\Response(
     *          response=200,
     *          description="Returns all the feautures collection based on the given bounding box coordinates( xmin,ymin,xmax,ymax)  and SDA number.",
     *       @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
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
     *     @OA\Parameter(
     *         name="bounding_box",
     *         in="path",
     *         description="List of WGS84 lat,lon cordinates in this order(xmin,ymin,xmax,ymax)",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="varchar",
     *         )
     *     ),
     *      @OA\Parameter(
     *         name="sda",
     *         in="path",
     *         description="SDA (stato di accatastamento) (e.g. 3 or 3,1 or 0,1,2). SDA=3 means ready to be validated, SDA=4 means validated by CAI expert",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="varchar"
     *         )
     *     ),
     * )
     *
     */
    public function hikingroutelist_collection(string $bb, string $sda)
    {
        $boundingBox = explode(',', $bb);
        $area = $this->getAreaBoundingBox(floatval($boundingBox[0]), floatval($boundingBox[1]), floatval($boundingBox[2]), floatval($boundingBox[3]));
        if ($area > _BOUNDIG_BOX_LIMIT)
            return response(['error' => "Bounding box is too large"], 500, ['Content-type' => 'application/json']);
        else {
            return HikingRoute::geojsonByBoundingBox($sda, floatval($boundingBox[0]), floatval($boundingBox[1]), floatval($boundingBox[2]), floatval($boundingBox[3]));
        }
    }

    public function getAreaBoundingBox($la0, $lo0, $la1, $lo1)
    {
        $res = DB::select(DB::raw("SELECT ST_area(ST_makeenvelope($la0,$lo0,$la1,$lo1))"));
        return floatval($res[0]->st_area);
    }
    public function createGeoJSONFromModel($item)
    {
        $obj = HikingRoute::where('id', '=', $item->id)
            ->select(
                DB::raw("ST_AsGeoJSON(geometry) as geom")
            )
            ->first();

        if (is_null($obj)) {
            return null;
        }

        $geom = $obj->geom;

        if (isset($geom)) {
            $response = [
                "type" => "Feature",
                "properties" => [
                    "id" => $item->id,
                    "relation_id" => $item->relation_id,
                    "source" => $item->source,
                    "cai_scale" => $item->cai_scale,
                    "from" => $item->from,
                    "to" => $item->to,
                    "ref" => $item->ref,
                    "public_page" => $item->getPublicPage(),
                    "sda" => $item->osm2cai_status,
                    "issues_status" => $item->issues_status ?? "",
                    "issues_description" => $item->issues_description ?? "",
                    "issues_last_update" => $item->issues_last_update ?? "",
                    // "name" => $item->name,
                    // "survey_date" => $item->survey_date,
                    // "rwn_name" => $item->rwn_name,
                    // "created_at" => $item->created_at,
                    // "updated_at" => $item->updated_at,
                    // "validation_date" => $item->validation_date,
                    // "user_id" => $item->user_id,
                    // "old_ref" => $item->old_ref,
                    // "source_ref" => $item->source_ref,
                    // "tags" => $item->tags,
                    // "osmc_symbol" => $item->osmc_symbol,
                    // "network" => $item->network,
                    // "roundtrip" => $item->roundtrip,
                    // "symbol" => $item->symbol,
                    // "symbol_it" => $item->symbol_it,
                    // "ascent" => $item->ascent,
                    // "descent" => $item->descent,
                    // "distance" => $item->distance,
                    // "duration_forward" => $item->duration_forward,
                    // "duration_backward" => $item->duration_backward,
                    // "operator" => $item->operator,
                    // "state" => $item->state,
                    // "description" => $item->description,
                    // "website" => $item->website,
                    // "wikimedia_commons" => $item->wikimedia_commons,
                    // "maintenance" => $item->maintenance,
                    // "note" => $item->note,
                    // "note_project_page" => $item->note_project_page,
                ],
                "geometry" => json_decode($geom, true)
            ];
            if ($item->osm2cai_status == 4)
                $response['properties']['validation_date'] = Carbon::create($item->validation_date)->format('Y-m-d');
            return $response;
        }
    }
}
