<?php

namespace App\Nova\Actions;

use App\Models\HikingRoute;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class ManageHikingRouteValidationAction extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $showOnDetail = true;

    public $name = 'MANAGE VALIDATION';

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $authUser = Auth::user();
        $user = User::find($authUser->id);
        $date = Carbon::now();

        if (! $user || $user == null) {
            return Action::danger(__('User info is not available'));
        }

        $model = $models->first();

        if (! $user->canManageHikingRoute($model)) {
            return Action::danger(__('You are not authorized to validate this hiking route'));
        }

        if ($model->osm2cai_status == 4) {
            $this->revertValidation($model);
        } elseif ($model->osm2cai_status == 3) {
            $this->validateSDA($model, $user, $date);
        }

        if (! $model->geometry_raw_data) {
            return Action::danger(__('Upload a Geometry file first!'));
        }

        if (! $model->is_geometry_correct) {
            return Action::danger(__('Geometry is not correct'));
        }

        return Action::redirect($model->id);
    }

    /**
     * Get the fields available on the action.
     *
     * @return array<int, \Laravel\Nova\Fields\Field>
     */
    public function fields(NovaRequest $request): array
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

    public static function getValidationConfirmText(HikingRoute $model)
    {
        if ($model->osm2cai_status == 4) {
            return __('Are you sure you want to revert the validation of this route? REF:').' '.$model->ref.' (REI CODE: '.$model->ref_REI.' / '.$model->ref_REI_comp.')';
        } elseif ($model->osm2cai_status == 3) {
            return __('Are you sure you want to validate this route? REF:').' '.$model->ref.' (REI CODE: '.$model->ref_REI.' / '.$model->ref_REI_comp.')';
        }

        return __('Are you sure you want to perform this action?');
    }

    public static function getValidationButtonText(HikingRoute $model)
    {
        if ($model->osm2cai_status == 4) {
            return __('Revert validation');
        } elseif ($model->osm2cai_status == 3) {
            return __('Validate');
        }

        return __('Confirm');
    }
}
