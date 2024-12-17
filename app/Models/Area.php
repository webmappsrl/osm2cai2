<?php

namespace App\Models;

use App\Jobs\CalculateIntersectionsJob;
use App\Models\HikingRoute;
use App\Models\Province;
use App\Models\Sector;
use App\Models\User;
use App\Traits\CsvableModelTrait;
use App\Traits\IntersectingRouteStats;
use App\Traits\SallableTrait;
use App\Traits\SpatialDataTrait;
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
        return $this->belongsToMany(HikingRoute::class, 'area_hiking_route');
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

    /**
     * Scope a query to only include areas owned by a certain user.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \App\Model\User  $user
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOwnedBy($query, User $user)
    {
        // Verify region
        if ($user->region) {
            $query->whereHas('province.region', function ($q) use ($user) {
                $q->where('id', $user->region->id);
            });
        }

        // Verify provinces
        if ($user->provinces->isNotEmpty()) {
            $query->orWhereHas('provinces', function ($q) use ($user) {
                $q->whereIn('id', $user->provinces->pluck('id'));
            });
        }

        // Verify areas
        if ($user->areas->isNotEmpty()) {
            $query->orWhereIn('id', $user->areas->pluck('id'));
        }

        return $query;
    }
}
