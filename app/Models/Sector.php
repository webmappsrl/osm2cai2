<?php

namespace App\Models;

use App\Models\User;
use App\Traits\CsvableModelTrait;
use App\Traits\SpatialDataTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sector extends Model
{
    use HasFactory, SpatialDataTrait, CsvableModelTrait;

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
