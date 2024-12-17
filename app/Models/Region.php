<?php

namespace App\Models;

use App\Jobs\CacheMiturAbruzzoDataJob;
use App\Jobs\CalculateIntersectionsJob;
use App\Models\CaiHut;
use App\Models\Club;
use App\Models\EcPoi;
use App\Models\HikingRoute;
use App\Models\MountainGroups;
use App\Models\Province;
use App\Models\User;
use App\Traits\AwsCacheable;
use App\Traits\CsvableModelTrait;
use App\Traits\IntersectingRouteStats;
use App\Traits\OsmfeaturesGeometryUpdateTrait;
use App\Traits\SallableTrait;
use App\Traits\SpatialDataTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Wm\WmOsmfeatures\Exceptions\WmOsmfeaturesException;
use Wm\WmOsmfeatures\Interfaces\OsmfeaturesSyncableInterface;
use Wm\WmOsmfeatures\Traits\OsmfeaturesSyncableTrait;

class Region extends Model implements OsmfeaturesSyncableInterface
{
    use HasFactory, OsmfeaturesSyncableTrait, OsmfeaturesGeometryUpdateTrait, CsvableModelTrait, SpatialDataTrait, AwsCacheable, SallableTrait, IntersectingRouteStats;

    protected $fillable = ['osmfeatures_id', 'osmfeatures_data', 'osmfeatures_updated_at', 'geometry', 'name', 'num_expected', 'hiking_routes_intersecting', 'code'];

    protected static $regionsCode = [
        'A' => 'Friuli-Venezia Giulia',
        'B' => 'Veneto',
        'C' => 'Trentino-Alto Adige',
        'D' => 'Lombardia',
        'E' => 'Piemonte',
        'F' => "Valle d'Aosta",
        'G' => 'Liguria',
        'H' => 'Emilia-Romagna',
        'L' => 'Toscana',
        'M' => 'Marche',
        'N' => 'Umbria',
        'O' => 'Lazio',
        'P' => 'Abruzzo',
        'Q' => 'Molise',
        'S' => 'Campania',
        'R' => 'Puglia',
        'T' => 'Basilicata',
        'U' => 'Calabria',
        'V' => 'Sicilia',
        'Z' => 'Sardegna',
    ];

    protected $casts = [
        'osmfeatures_updated_at' => 'datetime',
        'osmfeatures_data' => 'json',
    ];

    protected static function booted()
    {
        static::saved(function ($region) {
            if ($region->isDirty('geometry')) {
                CalculateIntersectionsJob::dispatch($region, HikingRoute::class)->onQueue('geometric-computations');
                CalculateIntersectionsJob::dispatch($region, MountainGroups::class)->onQueue('geometric-computations');
            }
        });

        static::updated(function ($region) {
            CacheMiturAbruzzoDataJob::dispatch('Region', $region->id);
        });
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function provinces()
    {
        return $this->hasMany(Province::class);
    }

    public function hikingRoutes()
    {
        return $this->belongsToMany(HikingRoute::class, 'hiking_route_region', 'region_id', 'hiking_route_id');
    }

    public function clubs()
    {
        return $this->hasMany(Club::class);
    }

    public function ecPois()
    {
        return $this->hasMany(EcPoi::class);
    }

    public function mountainGroups()
    {
        return $this->belongsToMany(MountainGroups::class, 'mountain_group_region', 'region_id', 'mountain_group_id');
    }

    public function caiHuts()
    {
        return $this->hasMany(CaiHut::class);
    }

    /**
     * Scope a query to only include models owned by a certain user.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \App\Model\User  $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOwnedBy($query, User $user)
    {
        $userModelId = $user->region ? $user->region->id : 0;

        return $query->where('id', $userModelId);
    }

    /**
     * Returns the OSMFeatures API endpoint for listing features for the model.
     */
    public static function getOsmfeaturesEndpoint(): string
    {
        return 'https://osmfeatures.maphub.it/api/v1/features/admin-areas/';
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
        return ['admin_level' => 4];
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
            Log::channel('wm-osmfeatures')->info('No data found for Region '.$osmfeaturesId);

            return;
        }

        // Update the geometry if necessary
        $updateData = self::updateGeometry($model, $osmfeaturesData, $osmfeaturesId);

        // Update the name if necessary
        $newName = $osmfeaturesData['properties']['name'] ?? null;
        if ($newName !== $model->name) {
            $updateData['name'] = $newName;
            Log::channel('wm-osmfeatures')->info('Name updated for Region '.$osmfeaturesId);
        }

        // Execute the update only if there are data to update
        if (! empty($updateData)) {
            $model->update($updateData);
        }

        //if the code column in the model is empty, run the assignCode command
        if (empty($model->code)) {
            $model->assignCode();
        }
    }

    /**
     * Generates a complete GeoJSON representation of all hiking routes in the region
     * that have a valid osm2cai_status (1-4).
     *
     * The GeoJSON includes detailed properties for each hiking route including:
     * - Basic info (id, name, ref, etc)
     * - Status and metadata
     * - Route details (distance, duration, elevation)
     * - Associated sectors
     * - Full geometry
     *
     * @return string JSON encoded GeoJSON FeatureCollection
     */
    public function getGeojsonComplete(): string
    {
        // Initialize GeoJSON structure
        $geojson = [
            'type' => 'FeatureCollection',
            'features' => [],
        ];

        // Get hiking routes with valid status
        $hikingRoutes = $this->hikingRoutes->where('osm2cai_status', '!=', 0);

        if (count($hikingRoutes)) {
            foreach ($hikingRoutes as $hikingRoute) {
                $osmfeaturesDataProperties = $hikingRoute->osmfeatures_data['properties'];
                // Get associated sectors for this route
                $sectors = $hikingRoute->sectors;

                // Build properties object
                $properties = [
                    // Basic info
                    'id' => $hikingRoute->id,
                    'name' => $hikingRoute->name,
                    'ref' => $hikingRoute->osmfeatures_data['properties']['ref'],
                    'old_ref' => $osmfeaturesDataProperties['old_ref'],
                    'source_ref' => $osmfeaturesDataProperties['source_ref'],

                    // Status and metadata
                    'created_at' => $hikingRoute->created_at,
                    'updated_at' => $hikingRoute->updated_at,
                    'osm2cai_status' => $hikingRoute->osm2cai_status,
                    'osm_id' => $osmfeaturesData['properties']['osm_id'],
                    'osm2cai' => url('/nova/resources/hiking-routes/'.$hikingRoute->id.'/edit'),
                    'survey_date' => $osmfeaturesDataProperties['survey_date'],
                    'accessibility' => $hikingRoute->issues_status,

                    // Route details
                    'from' => $osmfeaturesDataProperties['from'],
                    'to' => $osmfeaturesDataProperties['to'],
                    'distance' => $osmfeaturesDataProperties['distance'],
                    'cai_scale' => $osmfeaturesDataProperties['cai_scale'],
                    'roundtrip' => $osmfeaturesDataProperties['roundtrip'],
                    'duration_forward' => $osmfeaturesDataProperties['duration_forward'],
                    'duration_backward' => $osmfeaturesDataProperties['duration_backward'],
                    'ascent' => $osmfeaturesDataProperties['ascent'],
                    'descent' => $osmfeaturesDataProperties['descent'],

                    // REI references
                    'ref_REI' => $hikingRoute->osmfeatures_data['properties']['ref_REI'],

                    // Associated data
                    'sectors' => $sectors->pluck('name')->toArray(),
                ];

                // Get geometry as GeoJSON
                $geometry = HikingRoute::where('id', '=', $hikingRoute->id)
                    ->select(DB::raw('ST_AsGeoJSON(geometry) as geom'))
                    ->first()
                    ->geom;
                $geometry = json_decode($geometry, true);

                // Build complete feature
                $feature = [
                    'type' => 'Feature',
                    'properties' => $properties,
                    'geometry' => $geometry,
                ];

                $geojson['features'][] = $feature;
            }
        }

        return json_encode($geojson);
    }

    /**
     * Alias
     */
    public function children()
    {
        return $this->provinces();
    }

    /**
     * Get IDs of all child provinces.
     *
     * Alias method that calls provincesIds().
     *
     * @return array Array of province IDs belonging to this region
     */
    public function childrenIds()
    {
        return $this->provincesIds();
    }

    /**
     * Get IDs of all provinces belonging to this region.
     *
     * Retrieves the IDs of all provinces associated with this region
     * through the provinces relationship.
     *
     * @return array Array of province IDs belonging to this region
     */
    public function provincesIds(): array
    {
        return $this->provinces->pluck('id')->toArray();
    }

    /**
     * Get all area IDs associated with this region through its provinces.
     *
     * Iterates through all provinces belonging to this region and collects their area IDs.
     * The IDs are merged and duplicates are removed.
     *
     * @return array Array of unique area IDs belonging to this region's provinces
     */
    public function areasIds(): array
    {
        $result = [];
        foreach ($this->provinces as $province) {
            $result = array_unique(array_values(array_merge($result, $province->areasIds())));
        }

        return $result;
    }

    /**
     * Get all sector IDs associated with this region through its provinces.
     *
     * Iterates through all provinces belonging to this region and collects their sector IDs.
     * The IDs are merged and duplicates are removed.
     *
     * @return array Array of unique sector IDs belonging to this region's provinces
     */
    public function sectorsIds(): array
    {
        $result = [];
        foreach ($this->provinces as $province) {
            $result = array_unique(array_values(array_merge($result, $province->sectorsIds())));
        }

        return $result;
    }

    /**
     * Assigns a CAI region code to this region based on its name.
     *
     * Iterates through the predefined region codes and names, checking if the region's name
     * contains any of the predefined region names. When a match is found, assigns the
     * corresponding code and saves the model.
     *
     * The codes follow the CAI (Club Alpino Italiano) convention:
     * A = Friuli-Venezia Giulia, B = Veneto, C = Trentino-Alto Adige, etc.
     *
     * @return void
     */
    public function assignCode()
    {
        foreach (self::$regionsCode as $code => $name) {
            if (stripos($this->name, $name) !== false) {
                $this->code = $code;
                $this->saveQuietly();

                return;
            }
        }
    }
}
