<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Wm\WmPackage\Models\EcTrack;
use Wm\WmPackage\Models\EcPoiEcTrack;
use App\Models\SiHikingRoute;

class SiPoi extends EcPoi
{
    protected $table = 'ec_pois';

    /**
     * Usa lo stesso morph class di EcPoi per le relazioni polimorfiche (Media Library, ecc.).
     * In questo modo i media salvati con model_type = App\Models\EcPoi
     * sono visibili anche quando si lavora con il model SiPoi.
     */
    public function getMorphClass()
    {
        return EcPoi::class;
    }


    public function ecTracks(): BelongsToMany
    {
        return $this->belongsToMany(EcTrack::class, 'ec_poi_ec_track', 'ec_poi_id', 'ec_track_id')
            ->using(EcPoiEcTrack::class)
            ->withPivot('order')
            ->orderByPivot('order');
    }

    /**
     * Hiking routes (SI) collegate tramite la tabella hiking_route_ec_poi.
     */
    public function siHikingRoutes(): BelongsToMany
    {
        return $this->belongsToMany(SiHikingRoute::class, 'hiking_route_ec_poi', 'ec_poi_id', 'hiking_route_id');
    }
}
