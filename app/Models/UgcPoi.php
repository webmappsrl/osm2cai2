<?php

namespace App\Models;

use App\Models\UgcMedia;
use App\Models\User;
use App\Traits\SpatialDataTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class UgcPoi extends Model
{
    use HasFactory, SpatialDataTrait;

    protected $table = 'ugc_pois';

    protected $guarded = [];

    protected $casts = [
        'raw_data' => 'array',
        'validation_date' => 'datetime',
        'raw_data->date' => 'datetime:Y-m-d H:i:s',
    ];

    public function getRegisteredAtAttribute()
    {
        return isset($this->raw_data['date'])
            ? Carbon::parse($this->raw_data['date'])
            : $this->created_at;
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($model) {
            $model->user_id = auth()->id() ?? $model->user_id;
            $model->app_id = $model->app_id ?? 'osm2cai';
            $model->save();
        });
    }

    //getter for the name attribute
    public function getNameAttribute()
    {
        return $this->raw_data['title'] ?? $this->name ?? null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validator_id');
    }

    public function ugc_media(): HasMany
    {
        return $this->hasMany(UgcMedia::class);
    }

    /**
     * Return the json version of the ugc poi, avoiding the geometry
     *
     * @return array
     */
    public function getJsonProperties(): array
    {
        $array = $this->toArray();

        $propertiesToClear = ['geometry'];
        foreach ($array as $property => $value) {
            if (is_null($value) || in_array($property, $propertiesToClear)) {
                unset($array[$property]);
            }
        }

        if (isset($array['raw_data'])) {
            $array['raw_data'] = json_encode($array['raw_data']);
        }

        return $array;
    }

    /**
     * Create a geojson from the ugc poi
     *
     * @return array
     */
    public function getGeojson(): ?array
    {
        $feature = $this->getEmptyGeojson();
        if (isset($feature['properties'])) {
            $feature['properties'] = $this->getJsonProperties();

            return $feature;
        } else {
            return null;
        }
    }
}
