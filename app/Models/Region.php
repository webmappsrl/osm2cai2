<?php

namespace App\Models;

use App\Models\User;
use App\Models\EcPoi;
use App\Models\Province;
use App\Models\HikingRoute;
use App\Models\MountainGroups;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\RecalculateIntersections;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Stopwatch\Section;
use App\Traits\OsmfeaturesGeometryUpdateTrait;
use Wm\WmOsmfeatures\Traits\OsmfeaturesSyncableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Wm\WmOsmfeatures\Exceptions\WmOsmfeaturesException;
use Wm\WmOsmfeatures\Interfaces\OsmfeaturesSyncableInterface;

class Region extends Model implements OsmfeaturesSyncableInterface
{
    use HasFactory, OsmfeaturesSyncableTrait, OsmfeaturesGeometryUpdateTrait;

    protected $fillable = ['osmfeatures_id', 'osmfeatures_data', 'osmfeatures_updated_at', 'geometry', 'name', 'num_expected', 'hiking_routes_intersecting'];

    protected $casts = [
        'osmfeatures_updated_at' => 'datetime',
        'osmfeatures_data' => 'json',
        'hiking_routes_intersecting' => 'array',
    ];

    protected static function booted()
    {
        static::updated(function ($region) {
            if ($region->isDirty('geometry')) {
                RecalculateIntersections::dispatch($region);
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

    public function sections()
    {
        return $this->hasMany(Section::class);
    }

    public function ecPois()
    {
        return $this->hasMany(EcPoi::class);
    }

    public function mountainGroups()
    {
        return $this->belongsToMany(MountainGroups::class, 'mountain_groups_region', 'region_id', 'mountain_group_id');
    }

    public function caiHuts()
    {
        return $this->hasMany(CaiHuts::class);
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
            Log::channel('wm-osmfeatures')->info('No data found for Region ' . $osmfeaturesId);
            return;
        }

        // Update the geometry if necessary
        $updateData = self::updateGeometry($model, $osmfeaturesData, $osmfeaturesId);

        // Update the name if necessary
        $newName = $osmfeaturesData['properties']['name'] ?? null;
        if ($newName !== $model->name) {
            $updateData['name'] = $newName;
            Log::channel('wm-osmfeatures')->info('Name updated for Region ' . $osmfeaturesId);
        }

        // Execute the update only if there are data to update
        if (!empty($updateData)) {
            $model->update($updateData);
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
            'features' => []
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
                    'osm2cai' => url('/nova/resources/hiking-routes/' . $hikingRoute->id . '/edit'),
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
                    ->select(DB::raw("ST_AsGeoJSON(geometry) as geom"))
                    ->first()
                    ->geom;
                $geometry = json_decode($geometry, true);

                // Build complete feature
                $feature = [
                    'type' => 'Feature',
                    'properties' => $properties,
                    'geometry' => $geometry
                ];

                $geojson['features'][] = $feature;
            }
        }

        return json_encode($geojson);
    }
}
