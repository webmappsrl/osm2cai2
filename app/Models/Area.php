<?php

namespace App\Models;

use App\Models\User;
use App\Models\Sector;
use App\Models\Province;
use App\Models\HikingRoute;
use App\Traits\SallableTrait;
use App\Traits\SpatialDataTrait;
use App\Traits\CsvableModelTrait;
use App\Traits\IntersectingRouteStats;
use App\Jobs\CalculateIntersectionsJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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

    protected static function booted()
    {
        static::saved(function ($area) {
            if ($area->isDirty('geometry')) {
                CalculateIntersectionsJob::dispatch($area, HikingRoute::class)->onQueue('geometric-computations');
            }
        });
    }

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
