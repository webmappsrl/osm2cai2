<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MountainGroups extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'name',
        'description',
        'geometry',
        'aggregated_data',
        'intersectings',
    ];
}
