<?php

namespace App\Nova\Actions;

use App\Models\HikingRoute;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Imumz\LeafletMap\LeafletMap;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\Text;

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
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $user = auth()->user();

        if (!$user || $user == null)
            return Action::danger('User information not available');

        $roles = $user->getRoleNames()->toArray();
        $model = $models->first();

        if ($model->osm2cai_status != 4)
            return Action::danger('SDA is not 4!');

        if (!$user->canManageHikingRoute($model))
            return Action::danger('You do not have permissions for this route');

        $sectors = $model->sectors;
        $areas = $model->areas;
        $provinces = $model->provinces;

        $authorized = false;

        if (in_array('Administrator', $roles) || in_array('National Referent', $roles)) {
            $authorized = true;
        } elseif (in_array('Regional Referent', $roles) && $model->regions->pluck('id')->contains(auth()->user()->region->id)) {
            $authorized = true;
        } elseif (in_array('Local Referent', $roles) && (!$sectors->intersect($user->sectors)->isEmpty() || !$areas->intersect($user->areas)->isEmpty() || !$provinces->intersect($user->provinces)->isEmpty())) {
            $authorized = true;
        }

        if (!$authorized) {
            return Action::danger('You are not authorized to perform this action');
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
        if ($model->osm2cai_status == 4) {
            $model->osm2cai_status = 3;
            $model->validation_date = null;
            $model->user_id = null;
            $model->save();
        }
    }
}
