<?php

namespace App\Nova\Actions;

use Exception;
use Carbon\Carbon;
use App\Models\HikingRoute;
use App\Services\OsmService;
use Illuminate\Http\Request;
use Illuminate\Bus\Queueable;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\Text;
use Imumz\LeafletMap\LeafletMap;
use Laravel\Nova\Actions\Action;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Nova\Http\Requests\NovaRequest;

class OsmSyncHikingRouteAction extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $showOnDetail = true;

    public $name = 'SYNC WITH OSM DATA';

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {

        $user = auth()->user();

        /**
         * @var \App\Services\OsmService
         */
        $service = app()->make(OsmService::class);

        foreach ($models as $model) {
            if ($model->osm2cai_status > 3)
                return Action::danger('"Forcing the synchronization with OpenStreetMap is only possible if the route has an OSM2CAI status less than or equal to 3; if necessary, proceed first with REVERT VALIDATION"');
            $sectors = $model->sectors;
            $areas = $model->areas;
            $provinces = $model->provinces;
            if ($user->hasRole('Administrator') || $user->hasRole('National Referent')) {
                $service->updateHikingRouteModelWithOsmData($model);
                return Action::redirect('/resources/hiking-routes/' . $model->id);
            }
            if ($user->hasRole('Regional Referent')) {
                if ($model->regions->pluck('id')->contains($user->region->id)) {
                    $service->updateHikingRouteModelWithOsmData($model);
                    return Action::redirect('/resources/hiking-routes/' . $model->id);
                } else {
                    return Action::danger('You are not authorized to perform this action');
                }
            }
            if ($user->hasRole('Local Referent')) {
                if (!$sectors->intersect($user->sectors)->isEmpty()) {
                    $service->updateHikingRouteModelWithOsmData($model);
                    return Action::redirect('/resources/hiking-routes/' . $model->id);
                } else if (!$areas->intersect($user->areas)->isEmpty()) {
                    $service->updateHikingRouteModelWithOsmData($model);
                    return Action::redirect('/resources/hiking-routes/' . $model->id);
                } else if (!$provinces->intersect($user->provinces)->isEmpty()) {
                    $service->updateHikingRouteModelWithOsmData($model);
                    return Action::redirect('/resources/hiking-routes/' . $model->id);
                } else {
                    return Action::danger('You are not authorized to perform this action');
                }
            }
        }
        return Action::redirect('/resources/hiking-routes/' . $model->id);
    }



    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [];
    }
}
