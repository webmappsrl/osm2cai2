<?php

namespace App\Models;

use App\Jobs\CalculateIntersectionsJob;
use App\Models\HikingRoute;
use App\Models\User;
use App\Traits\CsvableModelTrait;
use App\Traits\IntersectingRouteStats;
use App\Traits\SallableTrait;
use App\Traits\SpatialDataTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sector extends Model
{
    use HasFactory, SpatialDataTrait, CsvableModelTrait, SallableTrait, IntersectingRouteStats;

    protected $guarded = [];

    protected static function booted()
    {
        static::saved(function ($sector) {
            if ($sector->isDirty('geometry')) {
                CalculateIntersectionsJob::dispatch($sector, HikingRoute::class)->onQueue('geometric-computations');
            }
        });
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function moderators()
    {
        return $this->belongsToMany(User::class);
    }

    public function hikingRoutes()
    {
        return $this->belongsToMany(HikingRoute::class, 'hiking_route_sector');
    }

    /**
     * Alias
     */
    public function parent()
    {
        return $this->area();
    }

    /**
     * Alias
     */
    public function children()
    {
        return $this->hikingRoutes();
    }

    /**
     * Scope a query to only include models owned by a certain user.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \App\Model\User  $user
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOwnedBy($query, User $user)
    {
        // Verify region
        if ($user->region) {
            $query->whereHas('area.province.region', function ($q) use ($user) {
                $q->where('id', $user->region->id);
            });
        }

        // Verify provinces
        if ($user->provinces->isNotEmpty()) {
            $query->orWhereHas('area.province', function ($q) use ($user) {
                $q->whereIn('id', $user->provinces->pluck('id'));
            });
        }

        // Verify areas
        if ($user->areas->isNotEmpty()) {
            $query->orWhereHas('area', function ($q) use ($user) {
                $q->whereIn('id', $user->areas->pluck('id'));
            });
        }

        // Verify sectors
        if ($user->sectors->isNotEmpty()) {
            $query->orWhereIn('id', $user->sectors->pluck('id'));
        }

        return $query;
    }

    /**
     * Generates a complete GeoJSON representation of all hiking routes in the sector
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

                // Build properties object
                $properties = [
                    // Basic info
                    'id' => $hikingRoute->id,
                    'created_at' => $hikingRoute->created_at,
                    'updated_at' => $hikingRoute->updated_at,
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
}
