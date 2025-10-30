<?php

namespace App\Nova;

use App\Models\EcPoi as EcPoiModel;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;
use Wm\WmPackage\Nova\EcPoi as WmEcPoi;

class EcPoi extends WmEcPoi
{
    public static $model = EcPoiModel::class;

    public static function indexQuery(NovaRequest $request, $query): Builder
    {
        $query = parent::indexQuery($request, $query);

        return $query->whereNotNull('app_id');
    }
}
