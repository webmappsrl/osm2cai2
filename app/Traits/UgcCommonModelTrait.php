<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

trait UgcCommonModelTrait
{
    /**
     * Common casts for UGC models
     */
    protected function getCommonCasts(): array
    {
        return [
            'validation_date' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'properties' => 'array',
        ];
    }

    /**
     * Initialize common casts - call this in the boot method or constructor
     */
    protected function initializeUgcCommonModelTrait()
    {
        $this->casts = array_merge($this->casts ?? [], $this->getCommonCasts());
    }

    /**
     * Boot trait per gestire eventi comuni
     */
    protected static function bootUgcCommonModelTrait()
    {
        // Quando si crea un nuovo UGC, assicurati che abbia la struttura form
        static::creating(function ($ugc) {
            if (! $ugc->properties) {
                $ugc->properties = [];
            }

            if (! isset($ugc->created_by)) {
                $ugc->created_by = 'platform';
            }

            // Imposta automaticamente user_id se non è già impostato
            if (! isset($ugc->user_id) || $ugc->user_id === null) {
                if (auth()->check()) {
                    $ugc->user_id = auth()->id();
                }
            }

            $properties = $ugc->properties;

            // Se non esiste la struttura form, creala
            if (! isset($properties['form'])) {
                $properties['form'] = [
                    'id' => null,
                ];
                $ugc->properties = $properties;
            }
        });
    }

    /**
     * Relazione con l'autore (User locale)
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relazione con l'utente (User locale) 
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relazione con App del wmpackage
     */
    public function app(): BelongsTo
    {
        return $this->belongsTo(\Wm\WmPackage\Models\App::class, 'app_id');
    }

    /**
     * Relazione con il validatore
     */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validator_id');
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

    /**
     * Accessor per registered_at con logica di fallback
     */
    public function getRegisteredAtAttribute()
    {
        return isset($this->raw_data['date'])
            ? Carbon::parse($this->raw_data['date'])
            : $this->created_at;
    }
} 