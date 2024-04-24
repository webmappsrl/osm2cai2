<?php

namespace App\Models;

use App\Traits\GeoIntersectTrait;
use App\Traits\GeojsonableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MountainGroups extends Model
{
    use HasFactory, GeojsonableTrait, GeoIntersectTrait;

    protected $fillable = [
        'id',
        'name',
        'description',
        'geometry',
        'aggregated_data',
        'intersectings',
    ];
}
