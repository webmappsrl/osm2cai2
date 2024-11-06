<?php

namespace App\Models;

use App\Models\User;
use App\Traits\GeoBufferTrait;
use App\Traits\GeojsonableTrait;
use App\Traits\CsvableModelTrait;
use App\Traits\GeoIntersectTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Sector extends Model
{
    use HasFactory, GeojsonableTrait, GeoBufferTrait, GeoIntersectTrait, CsvableModelTrait;

    protected $guarded = [];

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function hikingRoutes()
    {
        return $this->belongsToMany(HikingRoute::class);
    }
}
