<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NaturalSpring extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'loc_ref',
        'source',
        'source_ref',
        'source_code',
        'name',
        'region',
        'province',
        'municipality',
        'operator',
        'type',
        'volume',
        'time',
        'mass_flow_rate',
        'temperature',
        'conductivity',
        'survey_date',
        'lat',
        'lon',
        'elevation',
        'note',
        'geometry',
    ];
}
