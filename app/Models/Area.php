<?php

namespace App\Models;

use App\Models\HikingRoute;
use App\Models\Province;
use App\Models\Sector;
use App\Models\User;
use App\Traits\CsvableModelTrait;
use App\Traits\SpatialDataTrait;
use App\Traits\SallableTrait;
use App\Traits\IntersectingRouteStats;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    use HasFactory, SpatialDataTrait, CsvableModelTrait, SallableTrait, IntersectingRouteStats;

    protected $fillable = [
        'code',
        'name',
        'geometry',
        'full_code',
        'num_expected',
    ];

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function sectors()
    {
        return $this->hasMany(Sector::class);
    }

    public function sectorsIds(): array
    {
        return $this->sectors->pluck('id')->toArray();
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function hikingRoutes()
    {
        return $this->belongsToMany(HikingRoute::class);
    }

    /**
     * Alias
     */
    public function children()
    {
        return $this->sectors();
    }
    /**
     * Alias
     */
    public function childrenIds()
    {
        return $this->sectorsIds();
    }
    /**
     * Alias
     */
    public function parent()
    {
        return $this->province();
    }
}
