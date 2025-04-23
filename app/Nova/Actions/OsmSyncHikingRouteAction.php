<?php

namespace App\Nova\Actions;

use App\Models\HikingRoute;
use App\Services\OsmService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class OsmSyncHikingRouteAction extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $showOnDetail = true;

    public $name = 'SYNC WITH OSM DATA';

    /**
     * Perform the action on the given models.
     *
     * @param  ActionFields  $fields
     * @param  Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $user = auth()->user();
        $service = app(OsmService::class);

        foreach ($models as $model) {
            if ($model->osm2cai_status > 3) {
                return Action::danger('"Forcing the synchronization with OpenStreetMap is only possible if the route has an OSM2CAI status less than or equal to 3; if necessary, proceed first with REVERT VALIDATION"');
            }
            $sectors = $model->sectors;
            $areas = $model->areas;
            $provinces = $model->provinces;

            // Ensure osmfeatures_data and osm_id exist
            if (! isset($model->osmfeatures_data['properties']['osm_id'])) {
                return Action::danger('Hiking Route model '.$model->id.' does not have a valid osm_id in osmfeatures_data.');
            }

            if ($user->hasRole('Administrator') || $user->hasRole('National Referent')) {
                $service->updateHikingRouteModelWithOsmData($model);
                continue;
            }
            if ($user->hasRole('Regional Referent')) {
                if ($model->regions->pluck('id')->contains($user->region->id)) {
                    $service->updateHikingRouteModelWithOsmData($model);
                    continue;
                } else {
                    return Action::danger('You are not authorized to perform this action');
                }
            }
            if ($user->hasRole('Local Referent')) {
                if (! $sectors->intersect($user->sectors)->isEmpty()) {
                    $service->updateHikingRouteModelWithOsmData($model);
                    continue;
                } elseif (! $areas->intersect($user->areas)->isEmpty()) {
                    $service->updateHikingRouteModelWithOsmData($model);
                    continue;
                } elseif (! $provinces->intersect($user->provinces)->isEmpty()) {
                    $service->updateHikingRouteModelWithOsmData($model);
                    continue;
                } else {
                    return Action::danger('You are not authorized to perform this action');
                }
            }
        }

        // It always operates on a single model because showOnDetail is true
        return Action::redirect('/resources/hiking-routes/'.$models->first()->id);
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
