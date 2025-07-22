<?php

namespace App\Models;

use App\Traits\SpatialDataTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Wm\WmPackage\Models\UgcTrack as WmUgcTrack;

class UgcTrack extends WmUgcTrack
{
    protected $table = 'ugc_tracks';
    use SpatialDataTrait;

    protected $fillable = ['geohub_id', 'name', 'description', 'geometry', 'user_id', 'updated_at', 'raw_data', 'taxonomy_wheres', 'metadata', 'app_id'];

    protected $casts = [
        'validation_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'properties' => 'array',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }
    
    protected static function boot()
    {
        parent::boot();

           // Quando si crea un nuovo UGC, assicurati che abbia la struttura form
           static::creating(function ($ugcPoi) {
            if (! $ugcPoi->properties) {
                $ugcPoi->properties = [];
            }

            if (! isset($ugcPoi->created_by)) {
                $ugcPoi->created_by = 'platform';
            }

            $properties = $ugcPoi->properties;

            // Se non esiste la struttura form, creala
            if (! isset($properties['form'])) {
                $properties['form'] = [
                    'id' => null,
                ];
                $ugcPoi->properties = $properties;
            }
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
        return $this->belongsTo(User::class, 'user_id');
    }


        /**
     * Relazione con App
     */
    public function app(): BelongsTo
    {
        return $this->belongsTo(\Wm\WmPackage\Models\App::class, 'app_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validator_id');
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
     * Mutator per properties: crea automaticamente la struttura form se non esiste
     */
    public function setPropertiesAttribute($value)
    {
        // Assicurati che value sia un array
        $properties = is_array($value) ? $value : [];

        // Se non esiste la struttura form, creala
        if (! isset($properties['form'])) {
            $properties['form'] = [
                'id' => null, // Verrà impostato successivamente se necessario
            ];
        }

        // Se form esiste ma non ha un id, aCssicurati che abbia la struttura base
        if (isset($properties['form']) && ! isset($properties['form']['id'])) {
            $properties['form']['id'] = null;
        }

        $this->attributes['properties'] = json_encode($properties);
    }


       /**
     * Accessor to get the form data from properties
     */
    public function getFormAttribute()
    {
        return $this->properties['form'] ?? null;
    }

    /**
     * Accessor to get the form ID from properties
     */
    public function getFormIdAttribute()
    {
        return $this->properties['form']['id'] ?? null;
    }

}
