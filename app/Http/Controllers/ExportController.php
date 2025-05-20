<?php

namespace App\Http\Controllers;

use App\Http\Resources\ExportResource;
use App\Models\Area;
use App\Models\CaiHut;
use App\Models\CaiHuts;
use App\Models\Club;
use App\Models\EcPoi;
use App\Models\HikingRoute;
use App\Models\Itinerary;
use App\Models\MountainGroups;
use App\Models\NaturalSpring;
use App\Models\Section;
use App\Models\Sector;
use App\Models\UgcMedia;
use App\Models\UgcPoi;
use App\Models\UgcTrack;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * This controller handles the export of the data to OSM2CAI 2
 */
class ExportController extends Controller
{
    public function hikingRoutesList(): JsonResponse
    {
        /** @var Collection<int, HikingRoute> $hikingRoutes */
        $hikingRoutes = HikingRoute::all('id', 'updated_at');

        $data = $hikingRoutes->mapWithKeys(function (HikingRoute $hikingRoute) {
            return [$hikingRoute->id => $hikingRoute->updated_at];
        });

        return response()->json($data);
    }

    /**
     * Return a single hiking route with its features.
     *
     * @param  int  $id  the id of the hiking route
     */
    public function hikingRoutesSingleFeature(int $id): ExportResource
    {
        /** @var HikingRoute $hikingRoute */
        $hikingRoute = HikingRoute::find($id);

        return new ExportResource($hikingRoute);
    }

    /**
     * Return a list of users with their updated_at date.
     *
     * @return JsonResponse<array<int, string>>
     */
    public function UsersList(): JsonResponse
    {
        /** @var Collection<int, User> $users */
        $users = User::all('id', 'updated_at');

        $data = $users->mapWithKeys(function (User $user) {
            return [$user->id => $user->updated_at];
        });

        return response()->json($data);
    }

    /**
     * Return a single user with its features.
     *
     * @param  int  $id  the id of the user
     */
    public function UsersSingleFeature(int $id): ExportResource
    {
        /** @var User $user */
        $user = User::find($id);

        return new ExportResource($user);
    }

    /**
     * Return a list of UGC points of interest with their updated_at date.
     *
     * @return JsonResponse<array<int, string>>
     */
    public function UgcPoisList(): JsonResponse
    {
        /** @var Collection<int, UgcPoi> $ugcPois */
        $ugcPois = UgcPoi::all('id', 'updated_at');

        $data = $ugcPois->mapWithKeys(function (UgcPoi $ugcPoi) {
            return [$ugcPoi->id => $ugcPoi->updated_at];
        });

        return response()->json($data);
    }

    /**
     * Return a single UGC point of interest with its features.
     *
     * @param  int  $id  the id of the UGC poi
     */
    public function UgcPoisSingleFeature(int $id): ExportResource
    {
        /** @var UgcPoi $ugcPoi */
        $ugcPoi = UgcPoi::find($id);

        return new ExportResource($ugcPoi);
    }

    /**
     * Return a list of UGC tracks with their updated_at date.
     *
     * @return JsonResponse<array<int, string>>
     */
    public function UgcTracksList(): JsonResponse
    {
        /** @var Collection<int, UgcTrack> $tracks */
        $tracks = UgcTrack::all('id', 'updated_at');

        $data = $tracks->mapWithKeys(function (UgcTrack $track) {
            return [$track->id => $track->updated_at];
        });

        return response()->json($data);
    }

    /**
     * Return a single UGC track with its features.
     *
     * @param  int  $id  the id of the UGC track
     */
    public function UgcTracksSingleFeature(int $id): ExportResource
    {
        /** @var UgcTrack $track */
        $track = UgcTrack::find($id);

        return new ExportResource($track);
    }

    /**
     * Return a list of UGC media with their updated_at date.
     *
     * @return JsonResponse<array<int, string>>
     */
    public function UgcMediasList(): JsonResponse
    {
        /** @var Collection<int, UgcMedia> $media */
        $media = UgcMedia::all('id', 'updated_at');

        $data = $media->mapWithKeys(function (UgcMedia $medium) {
            return [$medium->id => $medium->updated_at];
        });

        return response()->json($data);
    }

    /**
     * Return a single UGC media with its features.
     *
     * @param  int  $id  the id of the UGC media
     */
    public function UgcMediasSingleFeature(int $id): ExportResource
    {
        /** @var UgcMedia $medium */
        $medium = UgcMedia::find($id);

        return new ExportResource($medium);
    }

    /**
     * Return a list of areas with their updated_at date.
     *
     * @return JsonResponse<array<int, string>>
     */
    public function AreasList(): JsonResponse
    {
        /** @var Collection<int, Area> $areas */
        $areas = Area::all('id', 'updated_at');

        $data = $areas->mapWithKeys(function (Area $area) {
            return [$area->id => $area->updated_at];
        });

        return response()->json($data);
    }

    /**
     * Return a single area with its features.
     *
     * @param  int  $id  the id of the area
     */
    public function AreasSingleFeature(int $id): ExportResource
    {
        /** @var Area $area */
        $area = Area::find($id);

        return new ExportResource($area);
    }

    /**
     * Return a list of sectors with their updated_at date.
     *
     * @return JsonResponse<array<int, string>>
     */
    public function SectorsList(): JsonResponse
    {
        /** @var Collection<int, Sector> $sectors */
        $sectors = Sector::all('id', 'updated_at');

        $data = $sectors->mapWithKeys(function (Sector $sector) {
            return [$sector->id => $sector->updated_at];
        });

        return response()->json($data);
    }

    /**
     * Return a single sector with its features.
     *
     * @param  int  $id  the id of the sector
     */
    public function SectorsSingleFeature(int $id): ExportResource
    {
        /** @var Sector $sector */
        $sector = Sector::find($id);

        return new ExportResource($sector);
    }

    /**
     * Return a list of sections with their updated_at date.
     *
     * @return JsonResponse<array<int, string>>
     */
    public function ClubsList(): JsonResponse
    {
        /** @var Collection<int, Club> $clubs */
        $clubs = Club::all('id', 'updated_at');

        $data = $clubs->mapWithKeys(function (Club $club) {
            return [$club->id => $club->updated_at];
        });

        return response()->json($data);
    }

    /**
     * Return a single section with its features.
     *
     * @param  int  $id  the id of the club
     */
    public function ClubsSingleFeature(int $id): ExportResource
    {
        /** @var Club $club */
        $club = Club::find($id);

        return new ExportResource($club);
    }

    /**
     * Return a list of mountain groups with their updated_at date.
     *
     * @return JsonResponse<array<int, string>>
     */
    public function MountainGroupsList(): JsonResponse
    {
        /** @var Collection<int, MountainGroups> $mountainGroups */
        $mountainGroups = MountainGroups::all('id', 'updated_at');

        $data = $mountainGroups->mapWithKeys(function (MountainGroups $mountainGroup) {
            return [$mountainGroup->id => $mountainGroup->updated_at];
        });

        return response()->json($data);
    }

    /**
     * Return a single mountain group with its features.
     *
     * @param  int  $id  the id of the mountain group
     */
    public function MountainGroupsSingleFeature(int $id): ExportResource
    {
        /** @var MountainGroups $mountainGroup */
        $mountainGroup = MountainGroups::find($id);

        return new ExportResource($mountainGroup);
    }

    /**
     * Return a list of natural springs with their updated_at date.
     *
     * @return JsonResponse<array<int, string>>
     */
    public function NaturalSpringsList(): JsonResponse
    {
        /** @var Collection<int, NaturalSpring> $naturalSprings */
        $naturalSprings = NaturalSpring::all('id', 'updated_at');

        $data = $naturalSprings->mapWithKeys(function (NaturalSpring $naturalSpring) {
            return [$naturalSpring->id => $naturalSpring->updated_at];
        });

        return response()->json($data);
    }

    /**
     * Return a single natural spring with its features.
     *
     * @param  int  $id  the id of the natural spring
     */
    public function NaturalSpringsSingleFeature(int $id): ExportResource
    {
        /** @var NaturalSpring $naturalSpring */
        $naturalSpring = NaturalSpring::find($id);

        return new ExportResource($naturalSpring);
    }

    /**
     * Return a list of itineraries with their updated_at date.
     *
     * @return JsonResponse<array<int, string>>
     */
    public function ItinerariesList(): JsonResponse
    {
        /** @var Collection<int, Itinerary> $itineraries */
        $itineraries = Itinerary::all('id', 'updated_at');

        $data = $itineraries->mapWithKeys(function (Itinerary $itinerary) {
            return [$itinerary->id => $itinerary->updated_at];
        });

        return response()->json($data);
    }

    /**
     * Return a single itinerary with its features.
     *
     * @param  int  $id  the id of the itinerary
     */
    public function ItinerariesSingleFeature(int $id): ExportResource
    {
        /** @var Itinerary $itinerary */
        $itinerary = Itinerary::find($id);

        return new ExportResource($itinerary);
    }

    /**
     * Return a list of Cai Huts with their updated_at date.
     *
     * @return JsonResponse<array<int, string>>
     */
    public function HutsList(): JsonResponse
    {
        /** @var Collection<int, CaiHuts> $caiHuts */
        $caiHuts = CaiHut::all('id', 'updated_at');

        $data = $caiHuts->mapWithKeys(function (CaiHut $caiHut) {
            return [$caiHut->id => $caiHut->updated_at];
        });

        return response()->json($data);
    }

    /**
     * Return a single Cai Hut with its features.
     *
     * @param  int  $id  the id of the Cai Hut
     */
    public function HutsSingleFeature(int $id): ExportResource
    {
        /** @var CaiHut $caiHut */
        $caiHut = CaiHut::find($id);

        return new ExportResource($caiHut);
    }

    /**
     * Return a list of EC POIs with their updated_at date.
     *
     * @return JsonResponse<array<int, string>>
     */
    public function EcPoisList(): JsonResponse
    {
        /** @var Collection<int, EcPoi> $ecPois */
        $ecPois = EcPoi::all('id', 'updated_at');

        $data = $ecPois->mapWithKeys(function (EcPoi $ecPoi) {
            return [$ecPoi->id => $ecPoi->updated_at];
        });

        return response()->json($data);
    }

    /**
     * Return a single EC POI with its features.
     *
     * @param  int  $id  the id of the EC POI
     */
    public function EcPoisSingleFeature(int $id): ExportResource
    {
        /** @var EcPoi $ecPoi */
        $ecPoi = EcPoi::find($id);

        if (is_null($ecPoi)) {
            abort(404);
        }

        return new ExportResource($ecPoi);
    }

    /**
     * Return a single EC POI with its features passing osmfeatures id
     */
    public function ecPoisSingleFeatureByOsmfeaturesId(string $osmfeaturesId): ExportResource
    {
        $ecPoi = EcPoi::where('osmfeatures_id', $osmfeaturesId)->first();

        if (is_null($ecPoi)) {
            abort(404);
        }

        return new ExportResource($ecPoi);
    }
}
