<?php

namespace App\Nova\Actions;

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
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $user = auth()->user();
        $service = app(OsmService::class);

        foreach ($models as $model) {

            if (! $user->canManageHikingRoute($model)) {
                return Action::danger('You are not authorized to perform this action');
            }

            if ($model->osm2cai_status > 3) {
                return Action::danger('"Forcing the synchronization with OpenStreetMap is only possible if the route has an OSM2CAI status less than or equal to 3; if necessary, proceed first with REVERT VALIDATION"');
            }

            // Ensure osmfeatures_data and osm_id exist
            if (! isset($model->osmfeatures_data['properties']['osm_id'])) {
                return Action::danger('Hiking Route model '.$model->id.' does not have a valid osm_id in osmfeatures_data.');
            }

            $service->updateHikingRouteModelWithOsmData($model);
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
