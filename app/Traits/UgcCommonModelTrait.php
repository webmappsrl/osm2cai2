<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
} 