<?php

namespace App\Http\Controllers;

use App\Models\UgcPoi;
use Illuminate\Http\Request;
use App\Http\Resources\PoiResource;
use App\Http\Resources\SignsResource;
use App\Http\Resources\PoiUmapResource;
use App\Http\Resources\SignUmapResource;
use App\Http\Resources\GeologicalSitesResource;
use App\Http\Resources\GeologicalSiteUmapResource;
use App\Http\Resources\ArchaeologicalAreasResource;
use App\Http\Resources\ArchaeologicalSitesResource;
use App\Http\Resources\ArchaeologicalAreaUmapResource;
use App\Http\Resources\ArchaeologicalSiteUmapResource;
use App\Http\Resources\UgcpoiUmapResource;

class UmapController extends Controller
{
    public function pois()
    {
        $pois = UgcPoi::where('form_id', 'poi')->whereIn('raw_data->waypointtype', ['flora', 'fauna', 'habitat'])->get();
        return response()->json(['type' => 'FeatureCollection', 'features' => UgcpoiUmapResource::collection($pois)]);
    }

    public function signs()
    {
        $signs = UgcPoi::where('form_id', 'signs')->get();
        return response()->json(['type' => 'FeatureCollection', 'features' => UgcpoiUmapResource::collection($signs)]);
    }

    public function archaeologicalSites()
    {
        $sites = UgcPoi::where('form_id', 'archaeological_site')->get();
        return response()->json(['type' => 'FeatureCollection', 'features' => UgcpoiUmapResource::collection($sites)]);
    }

    public function archaeologicalAreas()
    {
        $areas = UgcPoi::where('form_id', 'archaeological_area')->get();
        return response()->json(['type' => 'FeatureCollection', 'features' => UgcpoiUmapResource::collection($areas)]);
    }

    public function geologicalSites()
    {
        $sites = UgcPoi::where('form_id', 'geological_site')->get();
        return response()->json(['type' => 'FeatureCollection', 'features' => UgcpoiUmapResource::collection($sites)]);
    }
}
