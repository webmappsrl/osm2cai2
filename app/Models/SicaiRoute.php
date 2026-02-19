<?php

namespace App\Models;

use App\Models\Area;
use App\Models\CaiHut;
use App\Models\Club;
use App\Models\EcPoi;
use App\Models\Itinerary;
use App\Models\MountainGroups;
use App\Models\NaturalSpring;
use App\Models\Province;
use App\Models\Region;
use App\Models\Sector;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SicaiRoute extends HikingRoute
{
    // Usa la stessa tabella di HikingRoute (hiking_routes)
    // Il filtro per app_id viene gestito nella risorsa Nova

    /**
     * Override delle relazioni many-to-many per usare hiking_route_id invece di sicai_route_id
     * Laravel deduce automaticamente le foreign keys dal nome del modello, quindi dobbiamo specificarle esplicitamente
     */
    public function sectors(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class, 'hiking_route_sector', 'hiking_route_id', 'sector_id')
            ->withPivot(['percentage']);
    }

    public function regions(): BelongsToMany
    {
        return $this->belongsToMany(Region::class, 'hiking_route_region', 'hiking_route_id', 'region_id');
    }

    public function provinces(): BelongsToMany
    {
        return $this->belongsToMany(Province::class, 'hiking_route_province', 'hiking_route_id', 'province_id');
    }

    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'area_hiking_route', 'hiking_route_id', 'area_id');
    }

    public function clubs(): BelongsToMany
    {
        return $this->belongsToMany(Club::class, 'hiking_route_club', 'hiking_route_id', 'club_id');
    }

    public function itineraries(): BelongsToMany
    {
        return $this->belongsToMany(Itinerary::class, 'hiking_route_itinerary', 'hiking_route_id', 'itinerary_id');
    }

    public function nearbyCaiHuts(): BelongsToMany
    {
        return $this->belongsToMany(CaiHut::class, 'hiking_route_cai_hut', 'hiking_route_id', 'cai_hut_id')
            ->withPivot(['buffer']);
    }

    public function nearbyNaturalSprings(): BelongsToMany
    {
        return $this->belongsToMany(NaturalSpring::class, 'hiking_route_natural_spring', 'hiking_route_id', 'natural_spring_id')
            ->withPivot(['buffer']);
    }

    public function nearbyEcPois(): BelongsToMany
    {
        return $this->belongsToMany(EcPoi::class, 'hiking_route_ec_poi', 'hiking_route_id', 'ec_poi_id')
            ->withPivot(['buffer']);
    }

    public function ecPois(): BelongsToMany
    {
        return $this->belongsToMany(EcPoi::class, 'hiking_route_ec_poi', 'hiking_route_id', 'ec_poi_id')
            ->withPivot('order')
            ->orderByPivot('order');
    }

    public function mountainGroups(): BelongsToMany
    {
        return $this->belongsToMany(MountainGroups::class, 'mountain_group_hiking_route', 'hiking_route_id', 'mountain_group_id');
    }
}
