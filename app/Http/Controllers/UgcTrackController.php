<?php

namespace App\Http\Controllers;

use App\Http\Resources\UgcTrackResource;
use App\Models\App;
use App\Models\UgcMedia;
use App\Models\UgcTrack;
use App\Providers\HoquServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\UgcTrackCollection;
use App\Traits\UGCFeatureCollectionTrait;
use Exception;

class UgcTrackController extends Controller
{
    use UGCFeatureCollectionTrait;
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(Request $request)
    {
        $user = auth('api')->user();
        if (isset($user)) {

            if (!empty($request->header('app-id'))) {
                $reqAppId = $request->header('app-id');
                $appId = 'geohub_' . $reqAppId;
                $tracks = UgcTrack::where([['user_id', $user->id], ['app_id', $appId]])->orderByRaw('updated_at DESC')->get();
                return $this->getUGCFeatureCollection($tracks);
            }

            $tracks = UgcTrack::where('user_id', $user->id)->orderByRaw('updated_at DESC')->get();
            return $this->getUGCFeatureCollection($tracks);
        } else {
            return new UgcTrackCollection(UgcTrack::currentUser()->paginate(10));
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function store(Request $request): Response
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'type' => 'required',
            'properties' => 'required|array',
            'properties.name' => 'required|max:255',
            'properties.app_id' => 'required|max:255',
            'geometry' => 'required|array',
            'geometry.type' => 'required',
            'geometry.coordinates' => 'required|array',
        ]);

        if ($validator->fails())
            return response(['error' => $validator->errors(), 'Validation Error'], 400);

        $user = auth('api')->user();
        if (is_null($user))
            return response(['error' => 'User not authenticated'], 403);

        $track = new UgcTrack();
        $track->name = $data['properties']['name'];
        if (isset($data['properties']['description']))
            $track->description = $data['properties']['description'];
        $track->geometry = DB::raw("ST_GeomFromGeojson('" . json_encode($data['geometry']) . ")')");
        $track->user_id = $user->id;

        if (isset($data['properties']['app_id'])) {
            $appId = 'geohub_' . $data['properties']['app_id'];
            if ($appId)
                $track->app_id = $appId;
        }
        if (isset($data['properties']['metadata'])) {
            $track->metadata = json_encode($data['properties']['metadata'], JSON_PRETTY_PRINT);
            unset($data['properties']['metadata']);
        }

        if (isset($data['properties']['image_gallery']) && is_array($data['properties']['image_gallery']) && count($data['properties']['image_gallery']) > 0) {
            foreach ($data['properties']['image_gallery'] as $imageId) {
                if ($image = UgcMedia::find($imageId))
                    $track->ugc_media()->save($image);
            }
        }

        unset($data['properties']['image_gallery']);
        $track->raw_data = $data['properties'];
        $track->save();

        return response(['id' => $track->id, 'message' => 'Created successfully'], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Models\UgcTrack $ugcTrack
     *
     * @return Response
     */
    public function show(UgcTrack $ugcTrack)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\UgcTrack $ugcTrack
     *
     * @return Response
     */
    public function edit(UgcTrack $ugcTrack)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request              $request
     * @param \App\Models\UgcTrack $ugcTrack
     *
     * @return Response
     */
    public function update(Request $request, UgcTrack $ugcTrack)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\UgcTrack $ugcTrack
     *
     * @return Response
     */
    public function destroy($id)
    {
        try {
            $track = UgcTrack::find($id);
            $track->delete();
        } catch (Exception $e) {
            return response()->json([
                'error' => "this track can't be deleted by api",
                'code' => 400
            ], 400);
        }
        return response()->json(['success' => 'track deleted']);
    }

    public function geojson($ids)
    {
        $featureCollection = ['type' => 'FeatureCollection', 'features' => []];

        $ids = explode(',', $ids);
        $tracks = UgcTrack::whereIn('id', $ids)->get();

        foreach ($tracks as $track) {
            $feature = $track->getEmptyGeojson();
            $feature['properties'] = $track->getJson();
            $featureCollection['features'][] = $feature;
        }

        $headers = [
            'Content-type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="ugc_tracks.geojson"'
        ];

        return response()->json($featureCollection, 200, $headers);
    }
}
