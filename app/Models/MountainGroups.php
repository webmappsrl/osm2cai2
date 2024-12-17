<?php

namespace App\Models;

use App\Jobs\CacheMiturAbruzzoDataJob;
use App\Jobs\CalculateIntersectionsJob;
use App\Models\CaiHut;
use App\Models\Club;
use App\Models\HikingRoute;
use App\Models\Region;
use App\Traits\AwsCacheable;
use App\Traits\SpatialDataTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MountainGroups extends Model
{
    use HasFactory, SpatialDataTrait, AwsCacheable;

    protected $fillable = [
        'id',
        'name',
        'description',
        'geometry',
        'aggregated_data',
        'intersectings',
    ];

    protected static function booted()
    {
        static::saved(function ($mountainGroup) {
            if ($mountainGroup->isDirty('geometry')) {
                CalculateIntersectionsJob::dispatch($mountainGroup, Region::class)->onQueue('geometric-computations');
            }
        });

        static::updated(function ($mountainGroup) {
            if (app()->environment('production')) {
                CacheMiturAbruzzoDataJob::dispatch('MountainGroups', $mountainGroup->id);
            }
        });
    }

    public function regions()
    {
        return $this->belongsToMany(Region::class, 'mountain_group_region', 'mountain_group_id', 'region_id');
    }

    public function ecPois()
    {
        return $this->belongsToMany(EcPoi::class, 'mountain_group_ec_poi', 'mountain_group_id', 'ec_poi_id');
    }

    public function caiHuts()
    {
        return $this->belongsToMany(CaiHut::class, 'mountain_group_cai_hut', 'mountain_group_id', 'cai_hut_id');
    }

    public function hikingRoutes()
    {
        return $this->belongsToMany(HikingRoute::class, 'mountain_group_hiking_route', 'mountain_group_id', 'hiking_route_id');
    }

    public function clubs()
    {
        return $this->belongsToMany(Club::class, 'mountain_group_club', 'mountain_group_id', 'club_id');
    }
}
