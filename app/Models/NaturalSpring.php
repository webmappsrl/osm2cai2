<?php

namespace App\Models;

use App\Jobs\CheckNearbyHikingRoutesJob;
use App\Models\HikingRoute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NaturalSpring extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function booted()
    {
        static::saved(function ($spring) {
            if ($spring->isDirty('geometry') && !$spring->wasRecentlyCreated) {
                CheckNearbyHikingRoutesJob::dispatch($spring, config('osm2cai.hiking_route_buffer'))->onQueue('geometric-computations');
            }
        });
    }

    public function nearbyHikingRoutes()
    {
        return $this->belongsToMany(HikingRoute::class, 'hiking_route_natural_spring')->withPivot(['buffer']);
    }
}
