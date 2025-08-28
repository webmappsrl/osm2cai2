<?php

namespace App\Nova;

use Wm\WmPackage\Nova\EcPoi as WmEcPoi;
use App\Models\EcPoi as EcPoiModel;
use Laravel\Nova\Http\Requests\NovaRequest;
use Illuminate\Database\Eloquent\Builder;

class EcPoi extends WmEcPoi
{

    public static $model = EcPoiModel::class;

    public static function indexQuery(NovaRequest $request, $query): Builder
    {
        $query = parent::indexQuery($request, $query);
        return $query->whereNotNull('app_id');
    }
}
