<?php

namespace App\Http\Controllers;

use App\Models\Itinerary;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreItineraryRequest;
use App\Http\Requests\UpdateItineraryRequest;

class ItineraryController extends Controller
{
    /**
     * @OA\Get(
     *      path="/api/v2/itinerary/list",
     *      tags={"Api V2"},
     *      @OA\Response(
     *          response=200,
     *          description="Returns all the itinerary IDs and updated_at date. These ids can be used in the json API to retrieve the itinerary data.",
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
     *                     description="last update of itinerary",
     *                     type="date"
     *                 ),
     *                 example={1269:"2022-12-03 12:34:25",652:"2022-07-31 18:23:34",273:"2022-09-12 23:12:11"},
     *             )
     *         )
     *      ),
     *     )
     *
     */
    public function index()
    {
        $itinerary = collect(DB::select('SELECT id, updated_at FROM itineraries'));
        $data = $itinerary->pluck('updated_at', 'id')->toArray();

        return response()->json($data);
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
    public function store(StoreItineraryRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        $itinerary = Itinerary::find($id);

        return response()->json($itinerary->generateItineraryJson());
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Itinerary $itinerary)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateItineraryRequest $request, Itinerary $itinerary)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Itinerary $itinerary)
    {
        //
    }
}
