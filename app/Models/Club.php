<?php

namespace App\Models;

use App\Jobs\CacheMiturAbruzzoData;
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
        static::saved(function ($club) {
            CacheMiturAbruzzoData::dispatch('Club', $club->id);
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

    /**
     * Get the storage disk name to use for caching
     *
     * @return string The disk name
     */
    protected function getStorageDisk(): string
    {
        return 'wmfemitur-club';
    }
}
