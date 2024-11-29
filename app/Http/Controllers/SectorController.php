<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSectorRequest;
use App\Http\Requests\UpdateSectorRequest;
use App\Models\Sector;

class SectorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
    public function store(StoreSectorRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Sector $sector)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Sector $sector)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSectorRequest $request, Sector $sector)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Sector $sector)
    {
        //
    }

    /**
     * Returns a complete GeoJSON representation of all hiking routes in the sector.
     * 
     * @param string $id The ID of the sector
     * @return \Illuminate\Http\Response GeoJSON response with hiking routes data
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If sector not found
     */
    public function geojsonComplete(string $id): \Illuminate\Http\Response
    {
        try {
            $sector = Sector::findOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Sector not found'], 404);
        }

        $headers = [
            'Content-type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="osm2cai_' . date('Ymd') . '_settore_complete_' . $sector->name . '.geojson"',
        ];

        return response($sector->getGeojsonComplete(), 200, $headers);
    }
}
