<?php

namespace App\Http\Controllers;

use App\Http\Resources\HikingRouteResource;
use App\Http\Resources\HikingRouteTDHResource;
use App\Models\HikingRoute;
use App\Models\Region;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;

class HikingRouteController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v2/hiking-routes/list",
     *     summary="Get list of hiking routes",
     *     tags={"Api V2"},
     *     @OA\Response(
     *         response=200,
     *         description="List of hiking routes",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="1",
     *                 type="string",
     *                 format="date-time"
     *             ),
     *             @OA\Property(
     *                 property="2",
     *                 type="string",
     *                 format="date-time"
     *             ),
     *             @OA\Property(
     *                 property="3",
     *                 type="string",
     *                 format="date-time"
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $hikingRoutes = HikingRoute::orderBy('updated_at', 'desc')
            ->get(['id', 'updated_at'])
            ->mapWithKeys(function ($route) {
                return [$route->id => $route->updated_at->toISOString()];
            });

        return response()->json($hikingRoutes);
    }

    /**
     * @OA\Get(
     *      path="/api/v2/hiking-routes/region/{region_code}/{sda}",
     *      tags={"Api V2"},
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
     *                 @OA\Property(
     *                     property="updated_at",
     *                     description="last update od hiking route",
     *                     type="date"
     *                 ),
     *                 example={1269:"2022-12-03 12:34:25",652:"2022-07-31 18:23:34",273:"2022-09-12 23:12:11"},
     *             )
     *         )
     *      ),
     *     @OA\Parameter(
     *         name="region_code",
     *         in="path",
     *         description="Regione code according to CAI convention: <br/>
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
     *         description="SDA (stato di accatastamento) (e.g. 0,1,2,3,4). SDA=3 means ready to be validated, SDA=4 means validated by CAI expert",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="varchar"
     *         )
     *     ),
     * )
     */
    public function indexByRegion(string $region_code, string $sda)
    {
        $region_code = strtoupper($region_code);

        $sda = explode(',', $sda);

        // Check if region exists
        $region = Region::where('code', $region_code)->first();
        if (! $region) {
            return response(['error' => 'Region not found with code ' . $region_code], 404);
        }

        // Get hiking routes for region and status
        $list = HikingRoute::whereHas('regions', function ($query) use ($region_code) {
            $query->where('code', $region_code);
        })->whereIn('osm2cai_status', $sda)->get();

        if ($list->isEmpty()) {
            return response(['error' => 'No hiking routes found for region ' . $region_code . ' and SDA ' . implode(',', $sda)], 404);
        }

        $list = $list->pluck('id')->toArray();

        // Return
        return response($list, 200, ['Content-type' => 'application/json']);
    }

    /**
     * @OA\Get(
     *      path="/api/v2/hiking-routes/bb/{bounding_box}/{sda}",
     *      tags={"Api V2"},
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
     *                @OA\Property(
     *                     property="updated_at",
     *                     description="last update od hiking route",
     *                     type="date"
     *                 ),
     *                 example={1269:"2022-12-03 12:34:25",652:"2022-07-31 18:23:34",273:"2022-09-12 23:12:11"},
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
     *         description="SDA (stato di accatastamento) (e.g. 0,1,2,3,4). SDA=3 means ready to be validated, SDA=4 means validated by CAI expert",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="varchar"
     *         )
     *     ),
     * )
     */
    public function indexByBoundingBox(string $bounding_box, string $sda)
    {
        $list = DB::table('hiking_routes')
            ->whereRaw('ST_srid(geometry)=4326')
            ->whereRaw('ST_within(geometry,ST_MakeEnvelope(' . $bounding_box . ', 4326))')
            ->whereIn('osm2cai_status', explode(',', $sda))
            ->get();
        $data = [];
        foreach ($list as $hr) {
            $data[$hr->id] = Carbon::create($hr->updated_at)->format('Y-m-d H:i:s');
        }

        return response($data, 200, ['Content-type' => 'application/json']);
    }

    /**
     * @OA\Get(
     *      path="/api/v2/hiking-routes-osm/region/{region_code}/{sda}",
     *     tags={"Api V2"},
     *      @OA\Response(
     *          response=200,
     *          description="Returns all the hiking routes OSM IDs based on the given region code and SDA number.
     *                       OSMID can be used in hiking-route-osm API or directly in OpenStreetMap relation by the following URL:
     *                       https://openstreetmap.org/relation/{OSMID}. Remember that the data on OSM can be differente from data in
     *                       OSM2CAI after validation.",
     *      @OA\MediaType(
     *             mediaType="application/json",
     *
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="OSM",
     *                     description="Open Streen Map identification",
     *                     type="integer"
     *                 ),
     *                  @OA\Property(
     *                     property="updated_at",
     *                     description="last update od hiking route",
     *                     type="date"
     *                 ),
     *                 example={7766787:"2022-12-03 12:34:25",3151885:"2022-07-31 18:23:34",2736729:"2022-09-12 23:12:11"},
     *             )
     *         )
     *      ),
     *     @OA\Parameter(
     *         name="region_code",
     *         in="path",
     *         description="Regione code according to CAI convention: <br/>
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
     */
    public function OsmIndexByRegion(string $region_code, string $sda)
    {
        $region_code = strtoupper($region_code);

        $statuses = explode(',', $sda);
        $hikingRoutes = HikingRoute::whereHas('regions', function ($query) use ($region_code) {
            $query->where('code', $region_code);
        })->whereIn('osm2cai_status', $statuses)->get();

        $data = [];
        foreach ($hikingRoutes as $hr) {
            $osmId = is_array($hr->osmfeatures_data) ? $hr->osmfeatures_data['properties']['osm_id'] : json_decode($hr->osmfeatures_data, true)['properties']['osm_id'];
            $data[$osmId] = $hr->updated_at->format('Y-m-d H:i:s');
        }

        return response($data, 200, ['Content-type' => 'application/json']);
    }

    /**
     * @OA\Get(
     *      path="/api/v2/hiking-routes-osm/bb/{bounding_box}/{sda}",
     *      tags={"Api V2"},
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
     *                  @OA\Property(
     *                     property="updated_at",
     *                     description="last update od hiking route",
     *                     type="date"
     *                 ),
     *                  example={7766787:"2022-12-03 12:34:25",3151885:"2022-07-31 18:23:34",2736729:"2022-09-12 23:12:11"},
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
     *
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
     */
    public function OsmIndexByBoundingBox(string $bounding_box, string $sda)
    {
        $list = DB::table('hiking_routes')
            ->whereRaw('ST_srid(geometry)=4326')
            ->whereRaw('ST_within(geometry,ST_MakeEnvelope(' . $bounding_box . ', 4326))')
            ->whereIn('osm2cai_status', explode(',', $sda))
            ->get();
        $data = [];
        foreach ($list as $hr) {
            $osmfeaturesData = is_array($hr->osmfeatures_data) ? $hr->osmfeatures_data : json_decode($hr->osmfeatures_data, true);
            if (! is_array($osmfeaturesData)) {
                $osmfeaturesData = json_decode($osmfeaturesData, true);
            }
            $osmId = $osmfeaturesData['properties']['osm_id'];
            $data[$osmId] = Carbon::create($hr->updated_at)->format('Y-m-d H:i:s');
        }

        return response($data, 200, ['Content-type' => 'application/json']);
    }

    /**
     * @OA\Get(
     *      path="/api/v2/hiking-routes-collection/bb/{bounding_box}/{sda}",
     *      tags={"Api V2"},
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
     * "survey:CAI","cai_scale":"E","from":"Castellare","to":"Campo di Croce","ref":"117","public_page":"https://osm2cai.cai.it/hiking-route/id/2421","sda":4,"validation_date":"2022-07-29","updated_at":"2022-07-29 10:11:23"},"geometry":
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
     *         description="SDA (stato di accatastamento) (e.g. 0,1,2,3,4). SDA=3 means ready to be validated, SDA=4 means validated by CAI expert",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *             format="varchar"
     *         )
     *     ),
     * )
     */
    public function collectionByBoundingBox(string $bounding_box, string $sda)
    {
        $boundingBox = explode(',', $bounding_box);
        $area = DB::select('select ST_Area(ST_MakeEnvelope(' . $bounding_box . ', 4326)) as area')[0]->area;
        if ($area > 0.1) {
            return response(['error' => 'Bounding box is too large'], 500, ['Content-type' => 'application/json']);
        } else {
            return $this->geojsonByBoundingBox($sda, floatval($boundingBox[0]), floatval($boundingBox[1]), floatval($boundingBox[2]), floatval($boundingBox[3]));
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreHikingRouteRequest $request)
    {
        //
    }

    /**
     * @OA\Get(
     *      path="/api/v2/hiking-route/{id}",
     *      tags={"Api V2"},
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
     *                 @OA\Property(
     * property="properties",
     * type="object",
     * @OA\Property(property="id", type="integer", description="OSM2CAI ID"),
     * @OA\Property(property="relation_id", type="integer", description="OSMID"),
     * @OA\Property(property="source", type="string", description="from SDA=3 and over must be survey:CAI or other values accepted by CAI as valid source"),
     * @OA\Property(property="cai_scale", type="string", description="CAI scale difficulty: T E EE"),
     * @OA\Property(property="from", type="string", description="start point"),
     * @OA\Property(property="to", type="string", description="end point"),
     * @OA\Property(property="ref", type="string", description="local ref hiking route number must be three number and a letter only in last position for variants"),
     * @OA\Property(property="public_page", type="string", description="public url for the hiking route"),
     * @OA\Property(property="sda", type="integer", description="stato di accatastamento"),
     * @OA\Property(property="issues_status", type="string", description="issues status"),
     * @OA\Property(property="issues_description", type="string", description="issues description"),
     * @OA\Property(property="issues_last_update", type="date", description="date of last update of the issues format YYYY-mm-dd"),
     * @OA\Property(property="updated_at", type="date", description="date of last update of the hiking route format YYYY-mm-dd H:i:s"),
     * @OA\Property(property="validation_date", type="date", description="date of validation of the hiking route, visible only for sda = 4 format YYYY-mm-dd"),
     * @OA\Property(
     *property="itinerary",
     *type="array",
     *@OA\Items(
     *type="object",
     * @OA\Property(property="id", type="integer", description="the itinerary id"),
     *@OA\Property(property="name", type="string", description="the itinerary name"),
     *@OA\Property(
     *property="previous",
     *type="array",
     *@OA\Items(type="integer", description="the previous hiking route id")
     * ),
     *@OA\Property(
     *property="next",
     *type="array",
     *@OA\Items(type="integer", description="the next hiking route id")
     *      )
     *  )
     *)
     *)

     *                 ),
     *                 @OA\Property(property="geometry", type="object",
     *                      @OA\Property( property="type", type="string",  description="Postgis geometry types: LineString, MultiLineString"),
     *                      @OA\Property( property="coordinates", type="object",  description="hiking routes coordinates (WGS84)")
     *                 ),
     *                 example={"type":"Feature","properties":{"id":2421,"relation_id":4179533,"source":
     * "survey:CAI","cai_scale":"E","from":"Castellare","to":"Campo di Croce","ref":"117","public_page":"https://osm2cai.cai.it/hiking-route/id/2421","sda":4,"validation_date":"2022-07-29","updated_at":"2022-07-29 10:11:23","itinerary":{{"id":1,"name":"test","previous":20113,"next":"",}} },"geometry":
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
     */
    public function show(int $id)
    {
        try {
            $hikingRoute = HikingRoute::find($id);

            if (is_null($hikingRoute)) {
                return $this->notFoundResponse('No Hiking Route found with this id');
            }

            $geom = DB::select('SELECT ST_AsGeoJSON(geometry) as geom FROM hiking_routes WHERE id = ?', [$id])[0]->geom;

            if (! isset($geom)) {
                return $this->notFoundResponse('No geometry found for this Hiking Route');
            }

            $response = $this->buildHikingRouteResponse($hikingRoute, $geom);

            return response($response, 200, ['Content-type' => 'application/json']);
        } catch (Exception $e) {
            return $this->errorResponse('Error processing Hiking Route');
        }
    }

    /**
     * @OA\Get(
     *      path="/api/v2/hiking-route-tdh/{id}",
     *      tags={"Api V2"},
     *      @OA\Response(
     *          response=200,
     *          description="Returns the TDH format geojson of a Hiking Route based on the given ID.",
     *          @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="type",
     *                     description="Geojson type",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="properties",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", description="OSM2CAI ID"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time"),
     *                     @OA\Property(property="osm2cai_status", type="string"),
     *                     @OA\Property(property="validation_date", type="string"),
     *                     @OA\Property(property="relation_id", type="string"),
     *                     @OA\Property(property="ref", type="string"),
     *                     @OA\Property(property="ref_REI", type="string"),
     *                     @OA\Property(property="gpx_url", type="string"),
     *                     @OA\Property(property="cai_scale", type="string"),
     *                     @OA\Property(property="cai_scale_string", type="string"),
     *                     @OA\Property(property="cai_scale_description", type="string"),
     *                     @OA\Property(property="survey_date", type="string"),
     *                     @OA\Property(property="from", type="string"),
     *                     @OA\Property(property="city_from", type="string"),
     *                     @OA\Property(property="city_from_istat", type="string"),
     *                     @OA\Property(property="region_from", type="string"),
     *                     @OA\Property(property="region_from_istat", type="string"),
     *                     @OA\Property(property="to", type="string"),
     *                     @OA\Property(property="city_to", type="string"),
     *                     @OA\Property(property="city_to_istat", type="string"),
     *                     @OA\Property(property="region_to", type="string"),
     *                     @OA\Property(property="region_to_istat", type="string"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="roundtrip", type="string"),
     *                     @OA\Property(property="abstract", type="string"),
     *                     @OA\Property(property="distance", type="string"),
     *                     @OA\Property(property="ascent", type="string"),
     *                     @OA\Property(property="descent", type="string"),
     *                     @OA\Property(property="duration_forward", type="string"),
     *                     @OA\Property(property="duration_backward", type="string"),
     *                     @OA\Property(property="ele_from", type="string"),
     *                     @OA\Property(property="ele_to", type="string"),
     *                     @OA\Property(property="ele_max", type="string"),
     *                     @OA\Property(property="ele_min", type="string"),
     *                     @OA\Property(property="issues_status", type="string"),
     *                     @OA\Property(property="issues_last_update", type="string"),
     *                     @OA\Property(property="issues_description", type="string")
     *                 ),
     *                 @OA\Property(
     *                     property="geometry",
     *                     type="object",
     *                     @OA\Property(property="type", type="string"),
     *                     @OA\Property(property="coordinates", type="array", @OA\Items(type="array", @OA\Items(type="number")))
     *                 )
     *             )
     *         )
     *      ),
     *      @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="The OSM2CAI ID of a specific Hiking Route",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     )
     * )
     */
    public function showTdh(int $id)
    {
        return new HikingRouteTDHResource(HikingRoute::findOrFail($id));
    }

    /**
     * @OA\Get(
     *      path="/api/v2/hiking-route-osm/{id}",
     *      tags={"Api V2"},
     *      @OA\Response(
     *          response=200,
     *          description="Returns the geojson of a Hiking Route based on the given OSM ID.",
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
     *                     @OA\Property( property="validation_date", type="date",  description="date of validation of the hiking route, visible only for sda = 4 format YYYY-mm-dd"),
     *                     @OA\Property( property="updated_at", type="date", description="date of last update of the hiking route format YYYY-mm-dd H:i:s"),
     *
     *                 ),
     *                 @OA\Property(property="geometry", type="object",
     *                      @OA\Property( property="type", type="string",  description="Postgis geometry types: LineString, MultiLineString"),
     *                      @OA\Property( property="coordinates", type="object",  description="hiking routes coordinates (WGS84)")
     *                 ),
     *                 example={"type":"Feature","properties":{"id":2421,"relation_id":4179533,"source":
     * "survey:CAI","cai_scale":"E","from":"Castellare","to":"Campo di Croce","ref":"117","public_page":"https://osm2cai.cai.it/hiking-route/id/2421","sda":4,"validation_date":"2022-07-29","updated_at":"2022-07-29 10:11:23"},"geometry":
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
     */
    public function showByOsmId(int $osm_id)
    {
        try {
            $hr = HikingRoute::where('osmfeatures_data->properties->osm_id', '=', $osm_id)->first();

            if (is_null($hr)) {
                return $this->notFoundResponse('No Hiking Route found with this id');
            }

            if (! isset($hr->geometry)) {
                return $this->notFoundResponse('No geometry found for this Hiking Route');
            }

            $response = $this->buildHikingRouteResponse($hr, $hr->geometry);

            return response($response, 200, ['Content-type' => 'application/json']);
        } catch (Exception $e) {
            return $this->errorResponse('Error processing Hiking Route');
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(HikingRoute $hikingRoute)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateHikingRouteRequest $request, HikingRoute $hikingRoute)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(HikingRoute $hikingRoute)
    {
        //
    }

    // ------------------------------
    // Helper Methods
    // ------------------------------

    /**
     * Generate a 404 Not Found response with JSON content type
     *
     * @param string $message The error message to return
     * @return Response
     */
    private function notFoundResponse(string $message): Response
    {
        return response($message, 404, ['Content-type' => 'application/json']);
    }

    /**
     * Generate a 500 Internal Server Error response with JSON content type
     *
     * @param string $message The error message to return
     * @return Response
     */
    private function errorResponse(string $message): Response
    {
        return response($message, 500, ['Content-type' => 'application/json']);
    }

    /**
     * Build the GeoJSON response for a hiking route
     *
     * @param HikingRoute $hikingRoute The hiking route model
     * @param string $geom The geometry data as GeoJSON string
     * @return array The formatted response array
     */
    private function buildHikingRouteResponse(HikingRoute $hikingRoute, string $geom): array
    {
        $osmfeaturesProperties = $hikingRoute->osmfeatures_data['properties'];
        $response = [
            'type' => 'Feature',
            'properties' => [
                'id' => $hikingRoute->id,
                'relation_id' => $osmfeaturesProperties['osm_id'] ?? null,
                'source' => $osmfeaturesProperties['source'] ?? null,
                'cai_scale' => $osmfeaturesProperties['cai_scale'] ?? null,
                'from' => $osmfeaturesProperties['from'] ?? null,
                'to' => $osmfeaturesProperties['to'] ?? null,
                'ref' => $osmfeaturesProperties['ref'] ?? null,
                'public_page' => url('/hiking-route/id/' . $hikingRoute->id),
                'sda' => $hikingRoute->osm2cai_status,
                'issues_status' => $hikingRoute->issues_status ?? '',
                'issues_description' => $hikingRoute->issues_description ?? '',
                'issues_last_update' => $hikingRoute->issues_last_update ?? '',
                'updated_at' => $hikingRoute->updated_at->format('Y-m-d H:i:s'),
                'itinerary' => $this->getItineraryArray($hikingRoute),
            ],
            'geometry' => json_decode($geom, true),
        ];

        if ($hikingRoute->osm2cai_status == 4) {
            $response['properties']['validation_date'] = Carbon::create($hikingRoute->validation_date)->format('Y-m-d');
        }

        return $response;
    }

    /**
     * Generate array of itinerary data for a hiking route
     *
     * @param HikingRoute $hikingRoute The hiking route model
     * @return array Array of itinerary data with previous and next route info
     */
    private function getItineraryArray(HikingRoute $hikingRoute): array
    {
        $itinerary_array = [];
        $itineraries = $hikingRoute->itineraries()->get();

        foreach ($itineraries as $it) {
            $edges = $it->generateItineraryEdges();
            $prevRoute = $edges[$hikingRoute->id]['prev'] ?? null;
            $nextRoute = $edges[$hikingRoute->id]['next'] ?? null;

            $itinerary_array[] = [
                'id' => $it->id,
                'name' => $it->name,
                'previous' => $prevRoute[0] ?? '',
                'next' => $nextRoute[0] ?? '',
            ];
        }

        return $itinerary_array;
    }

    /**
     * Generate a GeoJSON collection of hiking routes by bounding box
     *
     * @param string $osm2cai_status The status of the hiking routes to include
     * @param string $lo0 The lower longitude of the bounding box
     * @param string $la0 The lower latitude of the bounding box
     * @param string $lo1 The upper longitude of the bounding box
     * @param string $la1 The upper latitude of the bounding box
     * @return string The GeoJSON collection as a string
     */
    public function geojsonByBoundingBox(string $osm2cai_status, string $lo0, string $la0, string $lo1, string $la1): string
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
                            'public_page' => url('/hiking-route/id/' . $item->id),
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
            throw new Exception('Error creating GeoJSON collection: ' . $e->getMessage());
        }
    }
}
