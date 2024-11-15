<?php

namespace App\Models;

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
        static::saved(function ($mountainGroup) {
            CacheMiturAbruzzoData::dispatch('MountainGroups', $mountainGroup->id);
        });
    }

    public function regions()
    {
        return $this->belongsToMany(Region::class, 'mountain_group_region', 'mountain_group_id', 'region_id');
    }

    /**
     * Get the storage disk name to use for caching
     * 
     * @return string The disk name
     */
    protected function getStorageDisk(): string
    {
        return 'wmfemitur-mountaingroup';
    }
}
