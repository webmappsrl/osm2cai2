<?php

namespace App\Http\Controllers;

use App\Models\HikingRoute;
use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\HikingRouteResource;

class HikingRouteController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v2/hiking-routes/list",
     *     summary="Ottieni la lista dei percorsi escursionistici",
     *     tags={"V2"},
     *     @OA\Response(
     *         response=200,
     *         description="Lista dei percorsi escursionistici",
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
        $hikingRoutes = HikingRoute::orderBy('updated_at', 'desc')->get(['id', 'updated_at']);
        return response()->json($hikingRoutes);
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
     * Display the specified resource.
     */
    public function show(HikingRoute $hikingRoute)
    {
        //
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
}
