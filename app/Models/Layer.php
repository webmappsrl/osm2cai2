<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Wm\WmPackage\Models\Layer as WmLayer;
use Wm\WmPackage\Models\Layerable;

class Layer extends WmLayer
{
    /**
     * Override the ecTracks relationship to use HikingRoute instead of EcTrack
     */
    public function ecTracks(): MorphToMany
    {
        return $this->morphedByMany(HikingRoute::class, 'layerable')->using(Layerable::class);
    }
}
