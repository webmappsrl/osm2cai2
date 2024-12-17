<?php

namespace App\Nova\Actions;

use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Models\HikingRoute;
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

class ValidateHikingRouteAction extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $showOnDetail = true;
    public $showOnIndex = false;

    public $name = 'VALIDATE';

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
        $date = Carbon::now();

        if (!$user || $user == null)
            return Action::danger('User info is not available');

        $model = $models->first();
        if (!$user->canManageHikingRoute($model))
            return Action::danger('You don\'t have permissions on this Hiking Route');
        if ($model->osm2cai_status != 3)
            return Action::danger('The SDA is not 3!');

        if (!$model->geometry_raw_data)
            return Action::danger('Upload a GPX first!');

        if (!$model->geometry_check)
            return Action::danger('Geometry is not correct');



        $this->validateSDA($model, $user, $date);

        return Action::message('SDA validated successfully');
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
        $model->validation_date = $date;
        $model->user_id = $user->id;
        $model->osm2cai_status = 4;
        $model->save();
    }
}
