<?php

namespace App\Http\Controllers;

use App\Http\Resources\UgcPoiCollection;
use App\Models\UgcMedia;
use App\Models\UgcPoi;
use App\Traits\UGCFeatureCollectionTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;

class UgcPoiController extends Controller
{
    use UGCFeatureCollectionTrait;
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
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
                $pois = UgcPoi::where([['user_id', $user->id], ['app_id', $appId]])->orderByRaw('updated_at DESC')->get();
                return $this->getUGCFeatureCollection($pois);
            }

            $pois = UgcPoi::where('user_id', $user->id)->orderByRaw('updated_at DESC')->get();
            return $this->getUGCFeatureCollection($pois);
        } else {
            return new UgcPoiCollection(UgcPoi::currentUser()->paginate(10));
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    //    public function create() {
    //    }

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

        $poi = new UgcPoi();
        $poi->name = $data['properties']['name'];
        if (isset($data['properties']['description']))
            $poi->description = $data['properties']['description'];
        $poi->geometry = DB::raw("ST_GeomFromGeojson('" . json_encode($data['geometry']) . ")')");
        $poi->user_id = $user->id;
        $poi->form_id = $data['properties']['id'];

        if (isset($data['properties']['app_id'])) {
            $appId = 'geohub_' . $data['properties']['app_id'];
            if ($appId)
                $poi->app_id = $appId;
        }

        unset($data['properties']['name']);
        unset($data['properties']['description']);
        unset($data['properties']['app_id']);
        $poi->raw_data = $data['properties'];
        $poi->save();

        if (isset($data['properties']['image_gallery']) && is_array($data['properties']['image_gallery']) && count($data['properties']['image_gallery']) > 0) {
            foreach ($data['properties']['image_gallery'] as $imageId) {
                if ($ugcMedia = UgcMedia::find($imageId))
                    $poi->ugc_media()->save($ugcMedia);
            }
        }

        unset($data['properties']['image_gallery']);
        $poi->raw_data = $data['properties'];
        $poi->save();

        return response(['id' => $poi->id, 'message' => 'Created successfully'], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param UgcPoi $ugcPoi
     *
     * @return Response
     */
    //    public function show(UgcPoi $ugcPoi) {
    //    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param UgcPoi $ugcPoi
     *
     * @return Response
     */
    //    public function edit(UgcPoi $ugcPoi) {
    //    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param UgcPoi  $ugcPoi
     *
     * @return Response
     */
    //    public function update(Request $request, UgcPoi $ugcPoi) {
    //    }

    /**
     * Remove the specified resource from storage.
     *
     * @param UgcPoi $ugcPoi
     *
     * @return Response
     */
    public function destroy($id)
    {
        try {
            $poi = UgcPoi::find($id);
            $poi->delete();
        } catch (Exception $e) {
            return response()->json([
                'error' => "this waypoint can't be deleted by api",
                'code' => 400
            ], 400);
        }
        return response()->json(['success' => 'waypoint deleted']);
    }

    public function geojson($ids)
    {
        $featureCollection = ['type' => 'FeatureCollection', 'features' => []];

        $ids = explode(',', $ids);
        $pois = UgcPoi::whereIn('id', $ids)->get();

        foreach ($pois as $poi) {
            $feature = $poi->getEmptyGeojson();
            $feature['properties'] = $poi->getJson();
            $featureCollection['features'][] = $feature;
        }

        $headers = [
            'Content-type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="ugc_pois.geojson"'
        ];

        return response()->json($featureCollection, 200, $headers);
    }
}
