<?php

namespace App\Nova\Actions;

use App\Models\HikingRoute;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;

class RevertValidateHikingRouteAction extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $showOnDetail = true;

    public function __construct()
    {
        $this->name = 'REVERT VALIDATION';
    }

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $user = auth()->user();

        if (! $user || $user == null) {
            return Action::danger('User information not available');
        }

        $model = $models->first();

        if ($model->osm2cai_status != 4) {
            return Action::danger('The SDA is not 4!');
        }

        if (! $user->canManageHikingRoute($model)) {
            return Action::danger('You are not authorized to revert the validation of this hiking route');
        }

        $this->revertValidation($model);

        return Action::redirect($model->id);
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields(Request $request)
    {
        return [];
    }

    private function revertValidation(HikingRoute $model)
    {
        $osmfeatures_data = $model->osmfeatures_data;

        // revert the status
        $osmfeatures_data['properties']['osm2cai_status'] = 3;
        $model->osm2cai_status = 3;

        // revert the validation date
        $model->validation_date = null;
        $model->validator_id = null;

        // save the model
        $model->osmfeatures_data = $osmfeatures_data;
        $model->save();
    }
}
