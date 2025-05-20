<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEcPoisRequest;
use App\Http\Requests\UpdateEcPoisRequest;
use App\Models\EcPoi;
use App\Models\HikingRoute;

class EcPoiController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v2/ecpois/bb/{bounding_box}/{type}",
     *     tags={"Api V2"},
     *     summary="Get Ec POIs by Bounding Box and Type",
     *     description="Returns a list of Eco POIs within the specified bounding box and of the specified type",
     *
     *     @OA\Parameter(
     *         name="bounding_box",
     *         in="path",
     *         required=true,
     *         description="Bounding box in 'minLng,minLat,maxLng,maxLat' format",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="type",
     *         in="path",
     *         required=true,
     *         description="Type of the POIs to retrieve: R for relation, W for way, N for node",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\AdditionalProperties(
     *                 type="string",
     *                 format="date-time"
     *             )
     *
     *         )
     *     )
     * )
     */
    public function indexByBoundingBox(string $boundingBox, string $type)
    {
        // $bounding_box should be in 'minLng,minLat,maxLng,maxLat' format
        [$minLng, $minLat, $maxLng, $maxLat] = explode(',', $boundingBox);
        $type = strtoupper($type);

        $pois = EcPoi::whereRaw(
            'ST_Within(geometry, ST_MakeEnvelope(?, ?, ?, ?, 4326))',
            [$minLng, $minLat, $maxLng, $maxLat]
        )->where('osmfeatures_data->properties->osm_type', $type)->get();

        $pois = $pois->mapWithKeys(function ($item) {
            return [$item['id'] => $item['updated_at']];
        });

        return response()->json($pois);
    }

    /**
     * @OA\Get(
     *     path="/api/v2/ecpois/{hr_osm2cai_id}/{type}",
     *     tags={"Api V2"},
     *     summary="Get EcPOIs in a 1km buffer from the HikingRoutes defined by ID",
     *     description="Returns a list of Ec POIs around 1km from a specific OSM2CAI hiking route ID and of a specified type",
     *
     *     @OA\Parameter(
     *         name="hr_osm2cai_id",
     *         in="path",
     *         required=true,
     *         description="OSM2CAI ID of the hiking route",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="type",
     *         in="path",
     *         required=true,
     *         description="Type of the POIs to retrieve",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\AdditionalProperties(
     *                 type="string",
     *                 format="date-time"
     *             )
     *         )
     *     )
     * )
     */
    public function indexByBufferFromHikingRouteId(string $id, string $type)
    {
        $pois = EcPoi::whereRaw(
            'ST_DWithin(geometry, (SELECT geometry FROM hiking_routes WHERE id = ?), 1000, true)',
            [$id]
        )->where('osmfeatures_data->properties->osm_type', $type)->get();

        return response()->json($pois);
    }

    /**
     * @OA\Get(
     *     path="/api/v2/ecpois/osm/{hr_osm_id}/{type}",
     *     tags={"Api V2"},
     *     summary="Get EcPOIs in a 1km buffer from the HikingRoutes defined by OSM ID",
     *     description="Returns a list of Ec POIs around 1km from a specific OSM hiking route ID and of a specified type",
     *
     *     @OA\Parameter(
     *         name="hr_osm_id",
     *         in="path",
     *         required=true,
     *         description="OSM ID of the hiking route",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="type",
     *         in="path",
     *         required=true,
     *         description="Type of the POIs to retrieve",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\AdditionalProperties(
     *                 type="string",
     *                 format="date-time"
     *             )
     *         )
     *     )
     * )
     */
    public function indexByBufferFromHikingRouteOsmId(string $osmId, string $type)
    {
        $hr = HikingRoute::where('osmfeatures_data->properties->osm_id', $osmId)->first();
        $pois = EcPoi::whereRaw(
            'ST_DWithin(geometry, ?, 1000, true)',
            [$hr->geometry]
        )->where('osmfeatures_data->properties->osm_type', $type)->get();

        return response()->json($pois);
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
    public function store(StoreEcPoisRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(EcPois $ecPois)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(EcPois $ecPois)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEcPoisRequest $request, EcPois $ecPois)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EcPois $ecPois)
    {
        //
    }
}
