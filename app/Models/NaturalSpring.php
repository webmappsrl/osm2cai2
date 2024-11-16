<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NaturalSpring extends Model
{
    use HasFactory;

    protected $guarded = [];

    // protected static function booted()
    // {
    //     static::saved(function ($spring) {
    //         Artisan::call('osm2cai:add_cai_huts_to_hiking_routes NaturalSpring ' . $spring->id);
    //     });

    //     static::created(function ($spring) {
    //         Artisan::call('osm2cai:add_cai_huts_to_hiking_routes NaturalSpring ' . $spring->id);
    //     });
    // }
}
