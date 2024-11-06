<?php

namespace App\Models;

use App\Models\User;
use App\Models\Sector;
use App\Models\Province;
use App\Models\HikingRoute;
use App\Traits\GeojsonableTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Area extends Model
{
    use HasFactory, GeojsonableTrait;

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
}
