<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRegionRequest;
use App\Http\Requests\UpdateRegionRequest;
use App\Models\Region;

class RegionController extends Controller
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
    public function store(StoreRegionRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Region $region)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Region $region)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRegionRequest $request, Region $region)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Region $region)
    {
        //
    }

    /**
     * Returns a complete GeoJSON representation of all hiking routes in the region.
     * 
     * @param string $id The ID of the region
     * @return \Illuminate\Http\Response GeoJSON response with hiking routes data
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If region not found
     */
    public function geojsonComplete(string $id): \Illuminate\Http\Response
    {
        try {
            $region = Region::findOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Region not found'], 404);
        }

        $headers = [
            'Content-type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="osm2cai_' . date('Ymd') . '_regione_complete_' . $region->name . '.geojson"',
        ];

        return response($region->getGeojsonComplete(), 200, $headers);
    }
}
