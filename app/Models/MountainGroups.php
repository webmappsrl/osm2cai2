<?php

namespace App\Models;

use App\Traits\MiturCacheable;
use App\Traits\SpatialDataTrait;
use App\Traits\GeoIntersectTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MountainGroups extends Model
{
    use HasFactory, SpatialDataTrait, GeoIntersectTrait, MiturCacheable;

    protected $fillable = [
        'id',
        'name',
        'description',
        'geometry',
        'aggregated_data',
        'intersectings',
    ];

    public function regions()
    {
        return $this->belongsToMany(Region::class, 'mountain_group_region', 'mountain_group_id', 'region_id');
    }
}
