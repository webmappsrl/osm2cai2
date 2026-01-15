<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SignageProjectable extends MorphPivot
{
    protected $table = 'signage_projectables';

    public $incrementing = true;

    /**
     * Get the parent model that the signage projectable is associated with.
     */
    public function model(): MorphTo
    {
        return $this->morphTo('signage_projectable');
    }

    /**
     * Get the signage project that owns the relationship.
     */
    public function signageProject()
    {
        return $this->belongsTo(SignageProject::class, 'signage_project_id');
    }
}
