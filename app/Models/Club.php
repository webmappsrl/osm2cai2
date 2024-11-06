<?php

namespace App\Models;

use App\Models\User;
use App\Models\Region;
use App\Models\HikingRoute;
use App\Traits\GeoBufferTrait;
use App\Traits\CsvableModelTrait;
use App\Traits\GeoIntersectTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Club extends Model
{
    use HasFactory, CsvableModelTrait, GeoIntersectTrait, GeoBufferTrait;

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
}
