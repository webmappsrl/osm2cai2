<?php

namespace App\Models;

use App\Jobs\CacheMiturAbruzzoDataJob;
use App\Models\HikingRoute;
use App\Models\Region;
use App\Models\User;
use App\Traits\AwsCacheable;
use App\Traits\CsvableModelTrait;
use App\Traits\SpatialDataTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Club extends Model
{
    use HasFactory, CsvableModelTrait, SpatialDataTrait, AwsCacheable;

    protected $table = 'clubs';

    protected $fillable = [
        'id',
        'name',
        'cai_code',
        'geometry',
        'addr_city',
        'addr_street',
        'addr_housenumber',
        'addr_postcode',
        'website',
        'phone',
        'email',
        'opening_hours',
        'wheelchair',
        'fax',
    ];

    protected static function booted()
    {
        static::updated(function ($club) {
            if (app()->environment('production')) {
                CacheMiturAbruzzoDataJob::dispatch('Club', $club->id);
            }
        });
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function hikingRoutes()
    {
        return $this->belongsToMany(HikingRoute::class, 'hiking_route_club');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function managerUsers()
    {
        return $this->hasMany(User::class, 'managed_club_id');
    }
}
