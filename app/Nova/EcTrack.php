<?php

namespace App\Nova;

use App\Models\HikingRoute as HikingRouteModel;
use Wm\WmPackage\Nova\EcTrack as WmEcTrack;

class EcTrack extends WmEcTrack
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<HikingRouteModel>
     */
    public static $model = HikingRouteModel::class;
}
