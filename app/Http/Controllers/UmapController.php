<?php

namespace App\Http\Controllers;

use App\Http\Resources\UgcpoiUmapResource;
use App\Models\UgcPoi;

class UmapController extends Controller
{
    public function pois()
    {
        $pois = UgcPoi::where('form_id', 'poi')
            ->whereRaw("LOWER(raw_data->>'waypointtype') IN (?, ?, ?)", ['flora', 'fauna', 'habitat'])
            ->whereNotNull('geometry')
            ->get();

        return response()->json(['type' => 'FeatureCollection', 'features' => UgcpoiUmapResource::collection($pois)]);
    }

    public function signs()
    {
        $signs = UgcPoi::where('form_id', 'signs')->whereNotNull('geometry')->get();

        return response()->json(['type' => 'FeatureCollection', 'features' => UgcpoiUmapResource::collection($signs)]);
    }

    public function archaeologicalSites()
    {
        $sites = UgcPoi::where('form_id', 'archaeological_site')->whereNotNull('geometry')->get();

        return response()->json(['type' => 'FeatureCollection', 'features' => UgcpoiUmapResource::collection($sites)]);
    }

    public function archaeologicalAreas()
    {
        $areas = UgcPoi::where('form_id', 'archaeological_area')->whereNotNull('geometry')->get();

        return response()->json(['type' => 'FeatureCollection', 'features' => UgcpoiUmapResource::collection($areas)]);
    }

    public function geologicalSites()
    {
        $sites = UgcPoi::where('form_id', 'geological_site')->whereNotNull('geometry')->get();

        return response()->json(['type' => 'FeatureCollection', 'features' => UgcpoiUmapResource::collection($sites)]);
    }
}
