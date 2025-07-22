<?php

namespace App\Models;

use App\Traits\SpatialDataTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class UgcTrack extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SpatialDataTrait;

    protected $fillable = ['geohub_id', 'name', 'description', 'geometry', 'user_id', 'updated_at', 'raw_data', 'taxonomy_wheres', 'metadata', 'app_id'];

    protected $casts = [
        'validation_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'properties' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::created(function ($model) {
            $model->user_id = auth()->id() ?? $model->user_id;
            $model->app_id ??= 'osm2cai';
            $model->save();
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getRegisteredAtAttribute()
    {
        return isset($this->raw_data['date'])
            ? Carbon::parse($this->raw_data['date'])
            : $this->created_at;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validator_id');
    }

    /**
     * Create a geojson from the ugc track
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

    /**
     * Return the json version of the ugctrack, avoiding the geometry
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
     * Relazione con App
     */
    public function app(): BelongsTo
    {
        return $this->belongsTo(\Wm\WmPackage\Models\App::class, 'app_id');
    }

}
