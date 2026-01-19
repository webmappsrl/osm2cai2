<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SignageProjectable extends MorphPivot
{
    protected $table = 'signage_projectables';

    public $incrementing = true;

    protected static function boot()
    {
        parent::boot();

        // Invalida la cache del SignageProject quando viene aggiunta/rimossa una hiking route
        static::created(function ($pivot) {
            if ($pivot->signage_project_id) {
                $signageProject = SignageProject::find($pivot->signage_project_id);
                if ($signageProject) {
                    $signageProject->clearFeatureCollectionMapCache();
                }
            }
        });

        static::deleted(function ($pivot) {
            if ($pivot->signage_project_id) {
                $signageProject = SignageProject::find($pivot->signage_project_id);
                if ($signageProject) {
                    $signageProject->clearFeatureCollectionMapCache();
                }
            }
        });
    }

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
