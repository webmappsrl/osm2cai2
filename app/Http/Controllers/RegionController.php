<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRegionRequest;
use App\Http\Requests\UpdateRegionRequest;
use App\Models\HikingRoute;
use App\Models\Region;
use Carbon\Carbon;
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
                ->selectRaw('
                    hiking_routes.id,
                    hiking_routes.name,
                    hiking_routes.osmfeatures_data,
                    hiking_routes.properties,
                    hiking_routes.issues_status,
                    hiking_routes.issues_description,
                    hiking_routes.issues_last_update,
                    hiking_routes.osm2cai_status,
                    hiking_routes.validation_date,
                    hiking_routes.updated_at,
                    ST_AsGeoJSON(geometry) as geom_geojson
                ');

            echo '{"type":"FeatureCollection","features":[';

            $first = true;
            foreach ($query->cursor() as $hikingRoute) {
                $sectors = $hikingRoute->sectors()->pluck('name')->toArray();
                $osmProps = $hikingRoute->osmfeatures_data['properties'] ?? [];

                $properties = array_merge($hikingRoute->properties ?? [], [
                    'id'                 => $hikingRoute->id,
                    'relation_id'        => $osmProps['osm_id'] ?? null,
                    'source'             => $osmProps['source'] ?? null,
                    'ref_REI'            => $osmProps['ref_REI'] ?? null,
                    'sda'                => $hikingRoute->osm2cai_status,
                    'osm2cai_status'     => $hikingRoute->osm2cai_status,
                    'issues_status'      => $hikingRoute->issues_status ?? '',
                    'issues_description' => $hikingRoute->issues_description ?? '',
                    'issues_last_update' => $hikingRoute->issues_last_update ?? '',
                    'updated_at'         => $hikingRoute->updated_at,
                    'public_page'        => url('/hiking-route/id/'.$hikingRoute->id),
                    'osm2cai'            => url('/nova/resources/hiking-routes/'.$hikingRoute->id.'/edit'),
                    'itinerary'          => $this->getItineraryArray($hikingRoute),
                    'sectors'            => $sectors,
                ]);

                if ($hikingRoute->osm2cai_status == 4) {
                    $properties['validation_date'] = $hikingRoute->validation_date
                        ? Carbon::parse($hikingRoute->validation_date)->format('Y-m-d')
                        : null;
                }

                $feature = [
                    'type'       => 'Feature',
                    'properties' => $properties,
                    'geometry'   => json_decode($hikingRoute->geom_geojson, true),
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

    private function getItineraryArray(HikingRoute $hikingRoute): array
    {
        $itinerary_array = [];
        $itineraries = $hikingRoute->itineraries()->get();

        foreach ($itineraries as $it) {
            $edges = $it->generateItineraryEdges();
            $prevRoute = $edges[$hikingRoute->id]['prev'] ?? null;
            $nextRoute = $edges[$hikingRoute->id]['next'] ?? null;

            $itinerary_array[] = [
                'id'       => $it->id,
                'name'     => $it->name,
                'previous' => $prevRoute[0] ?? '',
                'next'     => $nextRoute[0] ?? '',
            ];
        }

        return $itinerary_array;
    }
}
