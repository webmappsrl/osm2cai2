<?php

namespace App\Nova\Actions;

use App\Models\HikingRoute;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;

class ValidateHikingRouteAction extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $showOnDetail = true;

    public $showOnIndex = false;

    public $name = 'VALIDATE';

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $user = auth()->user();
        $date = Carbon::now();

        if (! $user || $user == null) {
            return Action::danger('User info is not available');
        }

        $model = $models->first();
        if (! $user->canManageHikingRoute($model)) {
            return Action::danger('You are not authorized to validate this hiking route');
        }
        if ($model->osm2cai_status != 3) {
            return Action::danger('The SDA is not 3!');
        }

        if (! $model->geometry_raw_data) {
            return Action::danger('Upload a Geometry file first!');
        }

        if (! $model->is_geometry_correct) {
            return Action::danger('Geometry is not correct');
        }

        $this->validateSDA($model, $user, $date);

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

    private function validateSDA(HikingRoute $model, User $user, Carbon $date)
    {
        // Update regular columns
        $model->validator_id = $user->id;
        $model->validation_date = $date;
        $model->osm2cai_status = 4;

        // Update the osmfeatures_data column
        $osmfeaturesData = $model->osmfeatures_data;
        $osmfeaturesData['properties']['osm2cai_status'] = 4;
        $model->osmfeatures_data = $osmfeaturesData;

        $model->save();
    }
}
