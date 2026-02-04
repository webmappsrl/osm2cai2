<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Osm2cai\SignageMap\Http\Controllers\SignageMapController;

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

            // Se è una hiking route associata al progetto e non ha checkpoint, imposta primo e ultimo palo come checkpoint
            if ($pivot->signage_projectable_type === HikingRoute::class && $pivot->signage_projectable_id) {
                $hikingRoute = HikingRoute::find($pivot->signage_projectable_id);
                if ($hikingRoute) {
                    $checkpoints = $hikingRoute->properties['signage']['checkpoint'] ?? [];
                    if (empty($checkpoints) || ! is_array($checkpoints)) {
                        app(SignageMapController::class)->setDefaultCheckpointsAndRefreshDirections($hikingRoute);
                    }
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
