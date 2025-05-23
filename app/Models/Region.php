<?php

namespace App\Models;

use App\Jobs\CalculateIntersectionsJob;
use App\Traits\AwsCacheable;
use App\Traits\CsvableModelTrait;
use App\Traits\IntersectingRouteStats;
use App\Traits\OsmfeaturesGeometryUpdateTrait;
use App\Traits\SallableTrait;
use App\Traits\SpatialDataTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Wm\WmOsmfeatures\Exceptions\WmOsmfeaturesException;
use Wm\WmOsmfeatures\Interfaces\OsmfeaturesSyncableInterface;
use Wm\WmOsmfeatures\Traits\OsmfeaturesSyncableTrait;

class Region extends Model implements OsmfeaturesSyncableInterface
{
    use AwsCacheable, CsvableModelTrait, HasFactory, IntersectingRouteStats, OsmfeaturesGeometryUpdateTrait, OsmfeaturesSyncableTrait, SallableTrait, SpatialDataTrait;

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
        return $this->belongsToMany(HikingRoute::class);
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

        // if the code column in the model is empty, run the assignCode command
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
        $hikingRoutes = $this->hikingRoutes() // Use the relationship query builder
            ->where('osm2cai_status', '!=', 0)
            ->with('sectors:id,name') // Eager load only necessary sector columns
            ->selectRaw('hiking_routes.*, ST_AsGeoJSON(geometry) as geom_geojson') // Select all HR fields + geometry
            ->get(); // Execute the optimized query

        if ($hikingRoutes->isNotEmpty()) {
            foreach ($hikingRoutes as $hikingRoute) {
                $osmfeaturesDataProperties = $hikingRoute->osmfeatures_data['properties'] ?? [];
                $sectors = $hikingRoute->sectors;

                $properties = [
                    'id' => $hikingRoute->id,
                    'name' => $hikingRoute->name,
                    'ref' => $osmfeaturesDataProperties['ref'] ?? null,
                    'old_ref' => $osmfeaturesDataProperties['old_ref'] ?? null,
                    'source_ref' => $osmfeaturesDataProperties['source_ref'] ?? null,
                    'created_at' => $hikingRoute->created_at,
                    'updated_at' => $hikingRoute->updated_at,
                    'osm2cai_status' => $hikingRoute->osm2cai_status,
                    'osm_id' => $osmfeaturesDataProperties['osm_id'] ?? null,
                    'osm2cai' => url('/nova/resources/hiking-routes/'.$hikingRoute->id.'/edit'),
                    'survey_date' => $osmfeaturesDataProperties['survey_date'] ?? null,
                    'accessibility' => $hikingRoute->issues_status, // Assuming issues_status is a direct attribute or accessor
                    'from' => $osmfeaturesDataProperties['from'] ?? null,
                    'to' => $osmfeaturesDataProperties['to'] ?? null,
                    'distance' => $osmfeaturesDataProperties['distance'] ?? null,
                    'cai_scale' => $osmfeaturesDataProperties['cai_scale'] ?? null,
                    'roundtrip' => $osmfeaturesDataProperties['roundtrip'] ?? null,
                    'duration_forward' => $osmfeaturesDataProperties['duration_forward'] ?? null,
                    'duration_backward' => $osmfeaturesDataProperties['duration_backward'] ?? null,
                    'ascent' => $osmfeaturesDataProperties['ascent'] ?? null,
                    'descent' => $osmfeaturesDataProperties['descent'] ?? null,
                    'ref_REI' => $osmfeaturesDataProperties['ref_REI'] ?? null,
                    'sectors' => $sectors->pluck('name')->toArray(),
                ];

                $geometry = json_decode($hikingRoute->geom_geojson, true);

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
