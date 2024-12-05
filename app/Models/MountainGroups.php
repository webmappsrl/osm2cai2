<?php

namespace App\Models;

use App\Models\Region;
use App\Traits\AwsCacheable;
use App\Traits\SpatialDataTrait;
use App\Jobs\CacheMiturAbruzzoDataJob;
use App\Jobs\CalculateIntersectionsJob;
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
}
