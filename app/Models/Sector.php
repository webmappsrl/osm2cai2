<?php

namespace App\Models;

use App\Traits\GeojsonableTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sector extends Model
{
    use HasFactory, GeojsonableTrait;

    protected $fillable = [
        'id',
        'name',
        'geometry',
        'code',
        'full_code',
        'num_expected',
        'human_name',
        'manager',
    ];
}
