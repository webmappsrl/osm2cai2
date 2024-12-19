<?php

namespace App\Models;

use App\Jobs\CacheMiturAbruzzoDataJob;
use App\Jobs\CheckNearbyHikingRoutesJob;
use App\Jobs\CheckNearbyHutsJob;
use App\Models\Club;
use App\Models\Region;
use App\Models\User;
use App\Traits\AwsCacheable;
use App\Traits\SpatialDataTrait;
use App\Traits\TagsMappingTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Wm\WmOsmfeatures\Exceptions\WmOsmfeaturesException;
use Wm\WmOsmfeatures\Interfaces\OsmfeaturesSyncableInterface;
use Wm\WmOsmfeatures\Traits\OsmfeaturesImportableTrait;

class EcPoi extends Model implements OsmfeaturesSyncableInterface
{
    use HasFactory, TagsMappingTrait, OsmfeaturesImportableTrait, SpatialDataTrait, AwsCacheable;

    protected $fillable = [
        'name',
        'geometry',
        'osmfeatures_id',
        'osmfeatures_data',
        'osmfeatures_updated_at',
        'type',
        'score',
        'user_id',
        'tags',
    ];

    protected $casts = [
        'osmfeatures_updated_at' => 'datetime',
        'osmfeatures_data' => 'json',
        'tags' => 'array',
    ];

    protected static function booted()
    {
        static::saved(function ($ecPoi) {
            if ($ecPoi->isDirty('geometry')) {
                CheckNearbyHikingRoutesJob::dispatch($ecPoi, config('osm2cai.hiking_route_buffer'))->onQueue('geometric-computations');
                CheckNearbyHutsJob::dispatch($ecPoi, config('osm2cai.cai_hut_buffer'))->onQueue('geometric-computations');
            }
        });

        static::updated(function ($ecPoi) {
            if (app()->environment('production')) {
                CacheMiturAbruzzoDataJob::dispatch('EcPoi', $ecPoi->id);
            }
        });
    }

    /**
     * Returns the OSMFeatures API endpoint for listing features for the model.
     */
    public static function getOsmfeaturesEndpoint(): string
    {
        return 'https://osmfeatures.maphub.it/api/v1/features/places/';
    }

    /**
     * Returns the query parameters for listing features for the model.
     *
     * The array keys should be the query parameter name and the values
     * should be the expected value.
     *
     * @return array<string,string>
     */
    public static function getOsmfeaturesListQueryParameters(): array
    {
        return [];
    }

    /**
     * Update the local database after a successful OSMFeatures sync.
     */
    public static function osmfeaturesUpdateLocalAfterSync(string $osmfeaturesId): void
    {
        $model = self::where('osmfeatures_id', $osmfeaturesId)->first();
        if (! $model) {
            throw WmOsmfeaturesException::modelNotFound($osmfeaturesId);
        }

        $osmfeaturesData = is_string($model->osmfeatures_data) ? json_decode($model->osmfeatures_data, true) : $model->osmfeatures_data;

        if (! $osmfeaturesData) {
            Log::channel('wm-osmfeatures')->info('No data found for Ec Poi '.$osmfeaturesId);

            return;
        }

        //format the geometry
        if ($osmfeaturesData['geometry']) {
            $geometry = DB::select("SELECT ST_AsText(ST_GeomFromGeoJSON('".json_encode($osmfeaturesData['geometry'])."'))")[0]->st_astext;
        } else {
            Log::channel('wm-osmfeatures')->info('No geometry found for Ec Poi '.$osmfeaturesId);
            $geometry = null;
        }

        if ($osmfeaturesData['properties']['name'] === null) {
            Log::channel('wm-osmfeatures')->info('No name found for Ec Poi '.$osmfeaturesId);
            $name = null;
        } else {
            $name = $osmfeaturesData['properties']['name'];
        }

        $model->update([
            'name' => $name,
            'geometry' => $geometry,
            'score' => $osmfeaturesData['properties']['score'],
            'type' => $model->getTagsMapping(),
        ]);
    }

    public function User()
    {
        return $this->belongsTo(User::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function mountainGroups()
    {
        return $this->belongsToMany(MountainGroups::class, 'mountain_group_ec_poi', 'ec_poi_id', 'mountain_group_id');
    }

    public function clubs()
    {
        return $this->belongsToMany(Club::class, 'ec_poi_club', 'ec_poi_id', 'club_id');
    }

    public function nearbyCaiHuts()
    {
        return $this->belongsToMany(CaiHut::class, 'ec_poi_cai_hut', 'ec_poi_id', 'cai_hut_id')->withPivot(['buffer']);
    }

    public function nearbyHikingRoutes()
    {
        return $this->belongsToMany(HikingRoute::class, 'hiking_route_ec_poi', 'ec_poi_id', 'hiking_route_id')->withPivot(['buffer']);
    }

    public function municipalities()
    {
        return $this->belongsToMany(Municipality::class, 'ec_poi_municipality', 'ec_poi_id', 'municipality_id');
    }
}
