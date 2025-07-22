<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Wm\WmPackage\Models\UgcPoi as WmUgcPoi;

class UgcPoi extends WmUgcPoi
{
    protected $table = 'ugc_pois';

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

    /**
     * The "booted" method of the model per gestire eventi
     */
    protected static function booted()
    {
        parent::booted();

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

    /**
     * Override the author relation to use the local User model
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Override the user relation to use the local User model
     */
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

        // Se form esiste ma non ha un id, assicurati che abbia la struttura base
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
