<?php

namespace App\Http\Controllers\V2;

use App\Models\EcPoi;
use App\Models\Region;
use App\Models\CaiHuts;
use App\Models\HikingRoute;
use Illuminate\Http\Request;
use App\Models\MountainGroups;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Section;
use Illuminate\Support\Facades\Cache;

class MiturAbruzzoController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v2/mitur_abruzzo/region_list",
     *     tags={"Api V2 - MITUR Abruzzo"},
     *     summary="List all regions",
     *     description="Returns a list of all regions with their ID and last updated timestamp. The update timestamp of an item in the database is formatted as 'YYYY-MM-DD-Thh:mm:ss+HH:M', adhering to the ISO8601 standard. This notation represents the date and time with an offset of hours (HH) and minutes (MM) from Coordinated Universal Time (UTC), ensuring a consistent formatting and interpretation across the globe.",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(
     *                     property="id",
     *                     type="integer",
     *                     description="The region ID"
     *                 ),
     *                 @OA\Property(
     *                     property="updated_at",
     *                     type="string",
     *                     format="date-time",
     *                     description="Last update timestamp of the region"
     *                 ),
     *                 example={12:"2022-12-03 12:34:25",6:"2022-07-31 18:23:34",2:"2022-09-12 23:12:11"},

     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found"
     *     ),
     * )
     */
    public function miturAbruzzoRegionList()
    {
        $regions = Region::all();

        $formattedRegions = $regions->mapWithKeys(function ($region) {

            $formattedDate = $region->updated_at->toIso8601String();

            return [$region->id => $formattedDate];
        });

        return response()->json($formattedRegions);
    }


    /**
     * @OA\Get(
     *     path="/api/v2/mitur_abruzzo/region/{id}",
     *     operationId="getRegionById",
     *     tags={"Api V2 - MITUR Abruzzo"},
     *     summary="Get Region by ID",
     *     description="Returns a single region, including mountain groups and region geometry, by ID. The update timestamp of an item in the database is formatted as 'YYYY-MM-DD-Thh:mm:ss+HH:M', adhering to the ISO8601 standard. This notation represents the date and time with an offset of hours (HH) and minutes (MM) from Coordinated Universal Time (UTC), ensuring a consistent formatting and interpretation across the globe.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the region to return",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 example="Feature"
     *             ),
     *             @OA\Property(
     *                 property="properties",
     *                 type="object",
     *                 @OA\Property(
     *                     property="id",
     *                     type="integer",
     *                     example=1
     *                 ),
     *                 @OA\Property(
     *                     property="name",
     *                     type="string",
     *                     example="Region Name"
     *                 ),
     *  @OA\Property(
     *                     property="description",
     *                     type="string",
     *                     example="Region Description"
     *                 ),
     *  @OA\Property(
     *                     property="abstract",
     *                     type="string",
     *                     example="Region abstract"
     *                 ),
     *               @OA\Property(
     *                     property="mountain_groups",
     *                     type="object",
     *                     example={"1": "2022-12-03 12:34:25", "2": "2023-01-15 09:30:00", "3": "2023-02-20 14:45:10"}
     *                 ),
     *   @OA\Property(
     *                     property="images",
     *                     type="array",
     *                     @OA\Items(
     *                         type="string",
     *                         example= "http://example.com/image.jpg",
     *                        description="The images url of the section"
     *                     ),
     *                 ),
     * 
     *             ),
     *               @OA\Property(property="geometry", type="object",
     *                      @OA\Property( property="type", type="string",  description="Postgis geometry type: MultiPolygon, etc."),
     *                      @OA\Property( property="coordinates", type="object",  description="region coordinates (WGS84)")
     *                 ),
     *               example={"type":"Feature","properties":{"id":1,"name":"Abruzzo", "description":"Abruzzo description","abstract":"Abruzzo abstract","mountain_groups":{"1":"2022-12-03 12:34:25","2":"2023-01-15 09:30:00","3":"2023-02-20 14:45:10"}, "images":{"http://example.com/image.jpg"}},"geometry":{"type":"MultiPolygon","coordinates":{{{10.4495294,43.7615252},{10.4495998,43.7615566}}}}}
     *        )
     *   ),
     *  @OA\Response(
     *    response=404,
     *  description="Region not found"
     * )
     * )
     */
    public function miturAbruzzoRegionById($id)
    {

        $region = Region::find($id);
        if (!$region) {
            return response()->json(['message' => 'Region not found'], 404);
        }
        $data = json_decode($region->cached_mitur_api_data, true);

        return response()->json($data);
    }


    /**
     * @OA\Get(
     *     path="/api/v2/mitur_abruzzo/mountain_group/{id}",
     *     operationId="getMountainGroupById",
     *     tags={"Api V2 - MITUR Abruzzo"},
     *     summary="Get Mountain Group by ID",
     *     description="Returns a single mountain group, including hiking routes, huts, pois, and sections that intersect with the mountain group geometry. The update timestamp of an item in the database is formatted as 'YYYY-MM-DD-Thh:mm:ss+HH:M', adhering to the ISO8601 standard. This notation represents the date and time with an offset of hours (HH) and minutes (MM) from Coordinated Universal Time (UTC), ensuring a consistent formatting and interpretation across the globe.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the mountain group to return",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 example="Feature"
     *             ),
     *             @OA\Property(
     *                 property="properties",
     *                 type="object",
     *                 @OA\Property(
     *                     property="id",
     *                     type="integer",
     *                     example=1
     *                 ),
     *                 @OA\Property(
     *                     property="name",
     *                     type="string",
     *                     example="Mountain Group Name",
     *                    description="The mountain group name"
     *                 ),
     *                 @OA\Property(
     *                     property="sections",
     *                     type="array",
     *                     @OA\Items(
     *                         type="integer",
     *                         example=1,
     *                        description="The section IDs associated with the mountain group"
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="area",
     *                     type="string",
     *                     example="123",
     *                   description="The area of the mountain group"
     *                 ),
     *  @OA\Property(
     *                     property="ele_min",
     *                     type="integer",
     *                     example= 123,
     *                   description= "The minimum elevation of the mountain group"
     *                 ),
     *  @OA\Property(
     *                     property="ele_max",
     *                     type="integer",
     *                     example=456,
     *                   description="The maximum elevation of the mountain group"
     *                 ),
     *    @OA\Property(
     *                     property="ele_avg",
     *                     type="integer",
     *                     example=322,
     *                   description="The average elevation of the mountain group"
     *                 ),
     *     @OA\Property(
     *                     property="ele_stdev",
     *                     type="integer",
     *                     example=212,
     *                   description="The standard deviation  of the mountain group's elevation"
     *                 ),
     *     @OA\Property(
     *                     property="slope_min",
     *                     type="integer",
     *                     example=10,
     *                   description="The minimum slope of the mountain group"
     *                 ),
     *  @OA\Property(
     *                     property="slope_max",
     *                     type="integer",
     *                     example=100,
     *                   description="The maximum slope of the mountain group"
     *                 ),
     *  @OA\Property(
     *                     property="slope_avg",
     *                     type="integer",
     *                     example=50,
     *                   description="The average slope of the mountain group"
     *                 ),
     *  @OA\Property(
     *                     property="slope_stdev",
     *                     type="integer",
     *                     example=20,
     *                   description="The standard deviation of the mountain group's slope"
     *                 ),
     * @OA\Property(
     * property="region",
     * type="string",
     * example="Lazio",
     * description="The region of the mountain group"
     * ),
     * @OA\Property(
     * property="provinces",
     * type="string",
     * example="Roma",
     * description="The provinces of the mountain group"
     * ),
     * @OA\Property(
     * property="municipalities",
     * type="string",
     * example="Roma",
     * description="The municipalities of the mountain group"
     * ),
     * @OA\Property(
     * property="map",
     * type="string",
     * example="url_mappa",
     * description="The map of the mountain group"
     * ),
     * @OA\Property(
     * property="description",
     * type="string",
     * example="Description of the mountain group",
     * description="The description of the mountain group"
     * ),
     * @OA\Property(
     * property="aggregated_data",
     * type="string",
     * example="aggregated data",
     * description="The aggregated data of the mountain group"
     * ),
     * @OA\Property(
     * property="protected_area",
     * type="string",
     * example="Parchi Aree protette Natura 2000",
     * description="The protected area of the mountain group"
     * ),
     * @OA\Property(
     * property="activity",
     * type="string",
     * example="Escursionismo, Alpinismo",
     * description="The activity of the mountain group"
     * ),
     * @OA\Property(
     * property="hiking_routes",
     * type="object",
     * example={"1": "2022-12-03 12:34:25", "2": "2023-01-15 09:30:00", "3": "2023-02-20 14:45:10"},
     * description="The hiking routes intersecting with the mountain group"
     * ),
     * @OA\Property(
     * property="ec_pois",
     * type="array",
     * @OA\Items(
     * type="integer",
     * example=1,
     * description="The EC POIs intersecting with the mountain group"
     * )
     * ),
     * @OA\Property(
     * property="cai_huts",
     * type="array",
     * @OA\Items(
     * type="integer",
     * example=1,
     * description="The CAI huts intersecting with the mountain group"
     * )
     * ),
     * @OA\Property(
     * property="hiking_routes_map",
     * type="string",
     * example="mappa percorsi",
     * description="The map of the hiking routes"
     * ),
     * @OA\Property(
     * property="disclaimer",
     * type="string",
     * example="testo disclaimer",
     * description="The disclaimer of the mountain group"
     * ),
     * @OA\Property(
     * property="ec_pois_count",
     * type="integer",
     * example=1,
     * description="The count of EC POIs intersecting with the mountain group"
     * ),
     * @OA\Property(
     * property="cai_huts_count",
     * type="integer",
     * example=1,
     * description="The count of CAI huts intersecting with the mountain group"
     * ),
     *   @OA\Property(
     *                     property="images",
     *                     type="array",
     *                     @OA\Items(
     *                         type="string",
     *                         example= "http://example.com/image.jpg",
     *                        description="The images url of the section"
     *                     ),
     *                 ),
     * 
     * ),
     * @OA\Property(property="geometry", type="object",
     * @OA\Property( property="type", type="string",  description="Postgis geometry type: MultiPolygon, etc."),
     * @OA\Property( property="coordinates", type="object",  description="mountain group coordinates (WGS84)")
     * ),
     * example={"type":"Feature","properties":{"id":1,"name":"Mountain Group Name","sections":{1},"area":"123","region":"Lazio","provinces":"Roma","municipalities":"Roma","map":"url_mappa","description":"Description of the mountain group","aggregated_data":"aggregated data","protected_area":"Parchi Aree protette Natura 2000","activity":"Escursionismo, Alpinismo", "hiking_routes": { "2806": "2024-02-24T03:48:14.000000Z" },"ec_pois":{1},"cai_huts":{1},"map":"mappa gruppo montuoso","hiking_routes_map":"mappa percorsi","disclaimer":"testo disclaimer","ec_pois_count":1,"cai_huts_count":1, "images":{"http://example.com/image.jpg"}},"geometry":{"type":"MultiPolygon","coordinates":{{{10.4495294,43.7615252},{10.4495998,43.7615566}}}}}       
     * )
     * ),
     * @OA\Response(
     * response=404,
     * description="Mountain group not found"
     * )
     * )
     */
    public function miturAbruzzoMountainGroupById($id)
    {

        $mountainGroup = MountainGroups::find($id);
        if (!$mountainGroup) {
            return response()->json(['message' => 'Mountain group not found'], 404);
        }

        $data = json_decode($mountainGroup->cached_mitur_api_data, true);

        return response()->json($data);
    }


    /**
     * @OA\Get(
     *     path="/api/v2/mitur_abruzzo/hiking_route/{id}",
     *     operationId="getHikingRouteById",
     *     tags={"Api V2 - MITUR Abruzzo"},
     *     summary="Get Hiking Route by ID",
     *     description="Returns a single hiking route, including cai_huts and pois that intersect with the hiking route geometry. The update timestamp of an item in the database is formatted as 'YYYY-MM-DD-Thh:mm:ss+HH:M', adhering to the ISO8601 standard. This notation represents the date and time with an offset of hours (HH) and minutes (MM) from Coordinated Universal Time (UTC), ensuring a consistent formatting and interpretation across the globe.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the hiking route to return",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 example="Feature"
     *             ),
     *             @OA\Property(
     *                 property="properties",
     *                 type="object",
     *                 @OA\Property(
     *                     property="id",
     *                     type="integer",
     *                     example=1,
     *                    description="The hiking route ID"
     *                 ),
     *                 @OA\Property(
     *                     property="ref",
     *                     type="string",
     *                     example="T001",
     *                    description="The hiking route reference"
     *                 ),
     *                 @OA\Property(
     *                     property="name",
     *                     type="string",
     *                     example="Sentiero 1",
     *                   description="The hiking route name"
     *                 ),
     *                 @OA\Property(
     *                     property="cai_scale",
     *                     type="string",
     *                     example="T",
     *                  description="The hiking route CAI scale"
     *                 ),
     *                 @OA\Property(
     *                     property="rwn_name",
     *                     type="string",
     *                     example="Sentiero 1",
     *                 description="The hiking route RWN name"
     *                ),
     *               @OA\Property(
     *                    property="section_id",
     *                   type="array",
     *                 @OA\Items(
     *                    type="integer",
     *                 example=1,
     *                description="The section IDs associated with the hiking route"
     *             )
     *            ),
     *          @OA\Property(
     *            property="from",
     *        type="string",
     *   example="Rifugio",
     * description="The starting point of the hiking route"
     * ),
     *  @OA\Property(
     *   property="from:coordinate",
     * type="string",
     * example="43.71699,10.51083",
     * description="The coordinates of the starting point of the hiking route"
     * ),
     * @OA\Property(
     * property="to",
     * type="string",
     * example="Rifugio",
     * description="The ending point of the hiking route"
     * ),
     * @OA\Property(
     * property="to:coordinate",
     * type="string",
     * example="43.71699,10.51083",
     * description="The coordinates of the ending point of the hiking route"
     * ),
     * @OA\Property(
     * property="abstract",
     * type="string",
     * example="1000",
     * description="The abstract of the hiking route"
     * ),
     * @OA\Property(
     * property="distance",
     * type="string",
     * example="10",
     * description="The distance of the hiking route"
     * ),
     * @OA\Property(
     * property="duration_forward",
     * type="string",
     * example="3",
     * description="The forward duration of the hiking route"
     * ),
     * @OA\Property(
     * property="ele_max",
     * type="string",
     * example="1000",
     * description="The maximum elevation of the hiking route"
     *  ),
     * @OA\Property(
     * property="ele_min",
     * type="string",
     * example="500",
     * description="The minimum elevation of the hiking route"
     * ),
     * @OA\Property(
     * property="incline",
     * type="string",
     * example="15%",
     * description="The incline of the hiking route"
     * ),
     * @OA\Property(
     * property="issues_status",
     * type="string",
     * example="ok",
     * description="The issues status of the hiking route"
     * ),
     * @OA\Property(
     * property="symbol",
     * type="string",
     * example="Segnaletica standard CAI",
     * description="The symbol of the hiking route"
     * ),
     * @OA\Property(
     * property="difficulty",
     * type="string",
     * example="Turistico",
     * description="The difficulty of the hiking route"
     * ),
     * @OA\Property(
     * property="info",
     * type="string",
     * example="Sezioni del Club Alpino Italiano, Guide Alpine o Guide Ambientali Escursionistiche",
     * description="The information of the hiking route"
     * ),
     * @OA\Property(
     * property="cai_huts",
     * type="array",
     * @OA\Items(
     * type="integer",
     * example=1,
     * description="The CAI huts intersecting with the hiking route"
     * )
     * ),
     * @OA\Property(
     * property="pois",
     * type="array",
     * @OA\Items(
     * type="integer",
     * example=1,
     * description="The POIs intersecting with the hiking route"
     * )
     * ),
     * @OA\Property(
     * property="activity",
     * type="string",
     * example="Escursionismo",
     * description="The activity of the hiking route"
     * ),
     * @OA\Property(
     * property="map",
     * type="string",
     * example="https://osm2cai.cai.it/hiking-route/id/9689",
     * description="The map of the hiking route"
     * ),
     *   @OA\Property(
     *                     property="images",
     *                     type="array",
     *                     @OA\Items(
     *                         type="string",
     *                         example= "http://example.com/image.jpg",
     *                        description="The images url of the section"
     *                     ),
     *                 ),
     * ),
     * @OA\Property(property="geometry", type="object",
     * @OA\Property( property="type", type="string",  description="Postgis geometry type: MultiLineString, etc."),
     * @OA\Property( property="coordinates", type="object",  description="hiking route coordinates (WGS84)")
     * ),
     * example={
     * "type": "Feature",
     * "properties": {
     * "id": 1,
     * "ref": "T001",
     * "name": "Sentiero 1",
     * "cai_scale": "T",
     * "rwn_name": "Sentiero 1",
     * "section_id": {292,393},
     * "from": "Rifugio",
     * "from:coordinate": "43.71699,10.51083",
     * "to": "Rifugio",
     * "to:coordinate": "43.71699,10.51083",
     * "abstract": {"gpx_url": "https://geohub.webmapp.it/api/ec/track/download/27456.gpx", "cai_scale_string": "escursionistico"},
     * "distance": "10",
     * "duration_forward": "3",
     * "ele_max": "1000",
     * "ele_min": "500",
     * "issues_status": "ok",
     * "symbol": "Segnaletica standard CAI",
     * "difficulty": "Turistico",
     * "info": "Sezioni del Club Alpino Italiano, Guide Alpine o Guide Ambientali Escursionistiche",
     * "cai_huts": {1},
     * "pois": {1},
     * "activity": "Escursionismo",
     * "map": "https://osm2cai.cai.it/hiking-route/id/9689",
     * "images": {"https://example.com/image.jpg", "https://example.com/image.jpg"},
     * },
     * "geometry": {
     * "type": "MultiLineString",
     * "coordinates": {
     * {
     * {
     * 10.4495294,
     * 43.7615252
     * },
     * {
     * 10.4495998,
     * 43.7615566
     * }
     * }
     * }
     * }
     * }
     * )
     * ),
     * @OA\Response(
     * response=404,
     * description="Hiking route not found"
     * )
     * )
     */
    public function miturAbruzzoHikingRouteById($id)
    {
        $hikingRoute = HikingRoute::find($id);
        if (!$hikingRoute) {
            return response()->json(['message' => 'Mountain group not found'], 404);
        }

        $data = json_decode($hikingRoute->cached_mitur_api_data, true);

        return response()->json($data);
    }




    /**
     * @OA\Get(
     *     path="/api/v2/mitur_abruzzo/hut/{id}",
     *     operationId="getMiturAbruzzoHutById",
     *     tags={"Api V2 - MITUR Abruzzo"},
     *     summary="Get Hut by ID",
     *     description="Returns a single hut by ID.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the hut to return",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 example="Feature"
     *             ),
     *             @OA\Property(
     *                 property="properties",
     *                 type="object",
     *                 @OA\Property(
     *                     property="id",
     *                     type="integer",
     *                     example=1,
     *                    description="The hut ID"
     *                 ),
     *                 @OA\Property(
     *                     property="name",
     *                     type="string",
     *                     example="Rifugio",
     *                  description="The hut name"
     *                 ),
     *                 @OA\Property(
     *                     property="type",
     *                     type="string",
     *                     example="Bivacco",
     *                description="The type of the hut"
     *                 ),
     *                 @OA\Property(
     *                     property="elevation",
     *                     type="integer",
     *                     example=2000,
     *                description="The elevation of the hut"
     *                 ),
     *                 @OA\Property(
     *                     property="mountain_groups",
     *                     type="integer",
     *                     example=1,
     *               description="The mountain groups associated with the hut"
     *                 ),
     *                 @OA\Property(
     *                     property="type_custodial",
     *                     type="string",
     *                     example="1",
     *               description="The type custodial of the hut"
     *                 ),
     *                 @OA\Property(
     *                    property="company_management_property",
     *                   type="string",
     *                 example="Cai",
     *               description="The company management property of the hut"
     *                ),
     *                @OA\Property(
     *                   property="addr:street",
     *                 type="string",
     *                example="via guglie alte",
     *              description="The street address of the hut"
     *              ),
     *             @OA\Property(
     *               property="addr:housenumber",
     *            type="string",
     *          example="23",
     *       description="The house number of the hut"
     *       ),
     *     @OA\Property(
     *      property="addr:postcode",
     *  type="string",
     * example="54787",
     * description="The postcode of the hut"
     * ),
     * @OA\Property(
     * property="addr:city",
     * type="string",
     * example="Alpi",
     * description="The city of the hut"
     * ),
     * @OA\Property(
     * property="ref:vatin",
     * type="string",
     * example="IT0000000000000",
     * description="The VATIN reference of the hut"
     * ),
     * @OA\Property(
     * property="phone",
     * type="string",
     * example="+39 000 00000000",
     * description="The phone number of the hut"
     * ),
     * @OA\Property(
     * property="fax",
     * type="string",
     * example="+39 000 00000001",
     * description="The fax number of the hut"
     * ),
     * @OA\Property(
     * property="email",
     * type="string",
     * example="example@email.com",
     * description="The email of the hut"
     * ),
     * @OA\Property(
     * property="email_pec",
     * type="string",
     * example="pec@email.com",
     * description="The PEC email of the hut"
     * ),
     * @OA\Property(
     * property="website",
     * type="string",
     * example="www.sito.it",
     * description="The website of the hut"
     * ),
     * @OA\Property(
     * property="facebook_contact",
     * type="string",
     * example="https://facebook.com/rifugio",
     * description="The facebook contact of the hut"
     * ),
     * @OA\Property(
     * property="municipality_geo",
     * type="string",
     * example="Alagna Valsesia",
     * description="The municipality of the hut"
     * ),
     * @OA\Property(
     * property="province_geo",
     * type="string",
     * example="Lucca",
     * description="The province of the hut"
     * ),
     * @OA\Property(
     * property="site_geo",
     * type="string",
     * example="Piemonte",
     * description="The site of the hut"
     * ),
     * @OA\Property(
     * property="source:ref",
     * type="string",
     * example="123456",
     * description="The source reference of the hut"
     * ),
     * @OA\Property(
     * property="description",
     * type="string",
     * example="Description of the hut",
     * description="The description of the hut"
     * ),
     * @OA\Property(
     * property="pois",
     * type="array",
     * @OA\Items(
     * type="integer",
     * example=1,
     * description="The POIs intersecting with the hut"
     * )
     * ),
     * @OA\Property(
     * property="opening",
     * type="string",
     * example="Mo-Th 10:00-18:00; Fr-Sa 10:00-19:00",
     * description="The opening hours of the hut"
     * ),
     * @OA\Property(
     * property="acqua_in_rifugio_service",
     * type="string",
     * example="1",
     * description="The water in the hut service. Possible values: 0, 1 where 0 = no and 1 = yes"
     * ),
     * @OA\Property(
     * property="acqua_calda_service",
     * type="string",
     * example="1",
     * description="The hot water service. Possible values: 0, 1 where 0 = no and 1 = yes"
     * ),
     * @OA\Property(
     * property="acqua_esterno_service",
     * type="string",
     * example="1",
     * description="The external water service. Possible values: 0, 1 where 0 = no and 1 = yes"
     * ),
     * @OA\Property(
     * property="posti_letto_invernali_service",
     * type="string",
     * example="12",
     * description="The winter bed places service"
     * ),
     * @OA\Property(
     * property="posti_totali_service",
     * type="string",
     * example="23",
     * description="The total bed places service"
     * ),
     * @OA\Property(
     * property="ristorante_service",
     * type="string",
     * example="1",
     * description="The restaurant service. Possible values: 0, 1 where 0 = no and 1 = yes"
     * ),
     * @OA\Property(
     * property="activities",
     * type="string",
     * example="Escursionismo/Alpinismo",
     * description="The activities of the hut"
     * ),
     * @OA\Property(
     * property="necessary_equipment",
     * type="string",
     * example="Normale dotazione Escursionistica / Normale dotazione Alpinistica",
     * description="The necessary equipment of the hut"
     * ),
     * @OA\Property(
     * property="rates",
     * type="string",
     * example="https://www.cai.it/wp-content/uploads/2022/12/23-2022-Circolare-Tariffario-rifugi-2023_signed.pdf",
     * description="The rates of the hut"
     * ),
     * @OA\Property(
     * property="payment_credit_cards",
     * type="string",
     * example="1",
     * description="The payment credit cards service. Possible values: 0, 1 where 0 = no and 1 = yes"
     * ),
     * @OA\Property(
     * property="hiking_routes",
     * type="array",
     * @OA\Items(
     * type="integer",
     * example=1,
     * description="The hiking routes intersecting with the hut"
     * )
     * ),
     * @OA\Property(
     * property="accessibilitá_ai_disabili_service",
     * type="string",
     * example="1",
     * description="The accessibility to disabled service. Possible values: 0, 1 where 0 = no and 1 = yes"
     * ),
     * @OA\Property(
     * property="rule",
     * type="string",
     * example="https://www.cai.it/wp-content/uploads/2020/12/Regolamento-strutture-ricettive-del-Club-Alpino-Italiano.pdf",
     * description="The rule of the hut"
     * ),
     *   @OA\Property(
     *                     property="images",
     *                     type="array",
     *                     @OA\Items(
     *                         type="string",
     *                         example= "http://example.com/image.jpg",
     *                        description="The images url of the section"
     *                     ),
     *                 ),
     *            ),
     *                @OA\Property(property="geometry", type="object",
     *                     @OA\Property( property="type", type="string",  description="Postgis geometry type: Point, etc."),
     *                    @OA\Property( property="coordinates", type="object",  description="hut coordinates (WGS84)")
     *               ),
     *       ),
     * ),
     * @OA\Response(
     * response=404,
     * description="Hut not found"
     * )
     * )
     */
    public function miturAbruzzoHutById($id)
    {
        $hut = CaiHuts::find($id);
        if (!$hut) {
            return response()->json(['message' => 'Mountain group not found'], 404);
        }

        $data = json_decode($hut->cached_mitur_api_data, true);

        return response()->json($data);
    }

    /**
     * @OA\Get(
     *     path="/api/v2/mitur_abruzzo/poi/{id}",
     *     operationId="getMiturAbruzzoPoiById",
     *     tags={"Api V2 - MITUR Abruzzo"},
     *     summary="Get POI by ID",
     *     description="Returns a single POI by ID.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the POI to return",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 example="Feature"
     *             ),
     *             @OA\Property(
     *                 property="properties",
     *                 type="object",
     *                 @OA\Property(
     *                     property="id",
     *                     type="integer",
     *                     example=1,
     *                    description="The POI ID"
     *                 ),
     *                 @OA\Property(
     *                     property="name",
     *                     type="string",
     *                     example="POI Name",
     *                   description="The POI name"
     *                 ),
     *                 @OA\Property(
     *                     property="type",
     *                     type="string",
     *                     example="POI Type",
     *                  description="The POI type"
     *                 ),
     *                 @OA\Property(
     *                     property="comune",
     *                     type="string",
     *                     example="Comune Name",
     *                 description="The comune name"
     *                 ),
     *                 @OA\Property(
     *                     property="description",
     *                     type="string",
     *                     example="Description of the POI",
     *                description="The POI description"
     *                 ),
     *                 @OA\Property(
     *                     property="info",
     *                     type="string",
     *                     example="Info about the POI",
     *                description="The POI info"
     *                 ),
     *                 @OA\Property(
     *                     property="difficulty",
     *                     type="string",
     *                     example="Difficulty of the POI",
     *               description="The POI difficulty"
     *                 ),
     *           @OA\Property(
     *             property="activity",
     *          type="string",
     *        example="Activity",
     *     description="The activity of the POI"
     *   ),
     * 
     * @OA\Property(
     * property="has_hiking_routes",
     * type="array",
     * @OA\Items(
     * type="integer",
     * example=1
     * )
     * ),
     * @OA\Property(
     * property="map",
     * type="string",
     * example="https://osm2cai.cai.it/poi/id/{}"
     * ),
     *   @OA\Property(
     *                     property="images",
     *                     type="array",
     *                     @OA\Items(
     *                         type="string",
     *                         example= "http://example.com/image.jpg",
     *                        description="The images url of the section"
     *                     ),
     *                 ),
     *           ),
     *               @OA\Property(property="geometry", type="object",
     *                    @OA\Property( property="type", type="string",  description="Postgis geometry type: Point, etc."),
     *                  @OA\Property( property="coordinates", type="object",  description="poi coordinates (WGS84)")
     *             ),
     *        ),
     *   ),
     * @OA\Response(
     * response=404,
     * description="POI not found"
     * )
     * )
     */
    public function miturAbruzzoPoiById($id)
    {
        $poi = EcPoi::find($id);
        if (!$poi) {
            return response()->json(['message' => 'poi not found'], 404);
        }
        $data = json_decode($poi->cached_mitur_api_data, true);

        return response()->json($data);
    }

    /**
     * @OA\Get(
     *     path="/api/v2/mitur_abruzzo/section/{id}",
     *     operationId="getMiturAbruzzoSectionById",
     *     tags={"Api V2 - MITUR Abruzzo"},
     *     summary="Get Section by ID",
     *     description="Returns a single section by ID. Coordinates are hardcoded for now. Will be implemented soon.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the section to return",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 example="Feature"
     *             ),
     *             @OA\Property(
     *                 property="properties",
     *                 type="object",
     *                 @OA\Property(
     *                     property="id",
     *                     type="integer",
     *                     example=1,
     *                   description="The section ID"
     *                 ),
     *                 @OA\Property(
     *                     property="name",
     *                     type="string",
     *                     example="Section Name",
     *                  description="The section name"
     *                 ),
     *                 @OA\Property(
     *                     property="addr:city",
     *                     type="string",
     *                     example="Rome",
     *                description="The city of the section"
     *                 ),
     *                   @OA\Property(
     *                     property="addr:housenumber",
     *                     type="string",
     *                     example="1",
     *                description="The house number of the section"
     *                 ),
     *                    @OA\Property(
     *                     property="addr:postcode",
     *                     type="string",
     *                     example="00100",
     *               description="The postcode of the section"
     *                 ),
     *                 @OA\Property(
     *                     property="addr:street",
     *                     type="string",
     *                     example="Via Roma",
     *               description="The street address of the section"
     *                 ),
     *                @OA\Property(
     *                    property="provinces",
     *                   type="string",
     *                  example="MOCKUP > Provincia",
     *                description="The provinces of the section"
     *             ),
     *                  @OA\Property(
     *                     property="source:ref",
     *                     type="string",
     *                     example="9219007",
     *               description="The source reference of the section"
     *                 ),
     *                    @OA\Property(
     *                     property="website",
     *                     type="string",
     *                     example="http://example.com",
     *              description="The website of the section"
     *                 ),
     *                 @OA\Property(
     *                     property="email",
     *                     type="string",
     *                     example="info@example.com",
     *              description="The email of the section"
     *                 ),
     *                 @OA\Property(
     *                     property="opening_hours",
     *                     type="string",
     *                     example="Mo-Fr 09:00-17:00",
     *             description="The opening hours of the section"
     *                 ),
     *                 @OA\Property(
     *                     property="phone",
     *                     type="string",
     *                     example="+390612345678",
     *             description="The phone number of the section"
     *                 ),
     *                 @OA\Property(
     *                     property="wheelchair",
     *                     type="string",
     *                     example="yes",
     *            description="The wheelchair accessibility of the section"
     *                 ),
     *                 @OA\Property(
     *                     property="fax",
     *                     type="string",
     *                     example="+390612345679",
     *           description="The fax number of the section"
     *                 ),
     *  @OA\Property(
     *                     property="images",
     *                     type="array",
     *                     @OA\Items(
     *                         type="string",
     *                         example= "http://example.com/image.jpg",
     *                        description="The images url of the section"
     *                     ),
     *                 ),
     *             ),
     *             @OA\Property(
     *                 property="geometry",
     *                 type="object",
     *                 @OA\Property(
     *                     property="type",
     *                     type="string",
     *                     example="Point",
     *                    description="Postgis geometry type: Point, etc."
     *                 ),
     *                 @OA\Property(
     *                     property="coordinates",
     *                     type="array",
     *                     @OA\Items(
     *                         type="number",
     *                         format="float",
     *                     ),
     *                     example={"longitude", "latitude"}
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Section not found"
     *     )
     * )
     */
    public function miturAbruzzoSectionById($id)
    {
        $section = Section::find($id);
        if (!$section) {
            return response()->json(['message' => 'section not found'], 404);
        }
        $data = json_decode($section->cached_mitur_api_data, true);

        return response()->json($data);
    }
}
