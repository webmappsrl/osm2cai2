<?php

namespace App\Models;

use App\Models\Region;
use App\Traits\AwsCacheable;
use App\Traits\SpatialDataTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
        static::updated(function ($mountainGroup) {
            if ($mountainGroup->isDirty('geometry')) {
                //recalculate intersections with regions
                CalculateIntersectionsJob::dispatch($mountainGroup, Region::class);
            }
            if (app()->environment('production')) {
                CacheMiturAbruzzoDataJob::dispatch('MountainGroups', $mountainGroup->id);
            }
        });
    }

    public function regions()
    {
        return $this->belongsToMany(Region::class, 'mountain_group_region', 'mountain_group_id', 'region_id');
    }
}
