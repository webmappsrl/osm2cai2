<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Wm\WmPackage\Models\UgcPoi as ModelsUgcPoi;

class WmUgcPoi extends ModelsUgcPoi
{
    protected $table = 'ugc_pois';
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
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
