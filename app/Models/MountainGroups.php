<?php

namespace App\Models;

use App\Traits\GeoIntersectTrait;
use App\Traits\SpatialDataTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MountainGroups extends Model
{
    use HasFactory, SpatialDataTrait, GeoIntersectTrait;

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
        return $this->belongsToMany(Region::class, 'mountain_groups_region', 'mountain_group_id', 'region_id');
    }
}
