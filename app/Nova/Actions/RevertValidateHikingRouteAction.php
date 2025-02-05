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
     * @param  ActionFields  $fields
     * @param  Collection  $models
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
        $model->updateQuietly([
            'osm2cai_status' => 3,
            'validation_date' => null,
            'validator_id' => null,
        ]);
    }
}
