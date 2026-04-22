<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRegionRequest;
use App\Http\Requests\UpdateRegionRequest;
use App\Models\Region;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     * @param  string  $id  The ID of the region
     * @return Response|JsonResponse GeoJSON response with hiking routes data
     *
     * @throws ModelNotFoundException If region not found
     */
    public function geojsonComplete(string $id): StreamedResponse|JsonResponse
    {
        try {
            $region = Region::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Region not found'], 404);
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="osm2cai_'.date('Ymd').'_regione_complete_'.$region->name.'.geojson"',
            'X-Accel-Buffering' => 'no',
        ];

        return response()->stream(function () use ($region) {
            $query = $region->hikingRoutes()
                ->where('osm2cai_status', '!=', 0)
                ->selectRaw('hiking_routes.id, hiking_routes.name, hiking_routes.osmfeatures_data, hiking_routes.issues_status, hiking_routes.osm2cai_status, hiking_routes.created_at, hiking_routes.updated_at, ST_AsGeoJSON(geometry) as geom_geojson');

            echo '{"type":"FeatureCollection","features":[';

            $first = true;
            foreach ($query->cursor() as $hikingRoute) {
                $sectors = $hikingRoute->sectors()->pluck('name')->toArray();
                $osmProps = $hikingRoute->osmfeatures_data['properties'] ?? [];

                $feature = [
                    'type' => 'Feature',
                    'properties' => [
                        'id' => $hikingRoute->id,
                        'name' => $hikingRoute->name,
                        'ref' => $osmProps['ref'] ?? null,
                        'old_ref' => $osmProps['old_ref'] ?? null,
                        'source_ref' => $osmProps['source_ref'] ?? null,
                        'created_at' => $hikingRoute->created_at,
                        'updated_at' => $hikingRoute->updated_at,
                        'osm2cai_status' => $hikingRoute->osm2cai_status,
                        'osm_id' => $osmProps['osm_id'] ?? null,
                        'osm2cai' => url('/nova/resources/hiking-routes/'.$hikingRoute->id.'/edit'),
                        'survey_date' => $osmProps['survey_date'] ?? null,
                        'accessibility' => $hikingRoute->issues_status,
                        'from' => $osmProps['from'] ?? null,
                        'to' => $osmProps['to'] ?? null,
                        'distance' => $osmProps['distance'] ?? null,
                        'cai_scale' => $osmProps['cai_scale'] ?? null,
                        'roundtrip' => $osmProps['roundtrip'] ?? null,
                        'duration_forward' => $osmProps['duration_forward'] ?? null,
                        'duration_backward' => $osmProps['duration_backward'] ?? null,
                        'ascent' => $osmProps['ascent'] ?? null,
                        'descent' => $osmProps['descent'] ?? null,
                        'ref_REI' => $osmProps['ref_REI'] ?? null,
                        'sectors' => $sectors,
                    ],
                    'geometry' => json_decode($hikingRoute->geom_geojson, true),
                ];

                if (! $first) {
                    echo ',';
                }
                echo json_encode($feature);
                $first = false;

                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }

            echo ']}';
        }, 200, $headers);
    }
}
