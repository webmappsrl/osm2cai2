<?php

namespace App\Nova\Actions;

use App\Models\HikingRoute;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Http\Requests\NovaRequest;

class UploadValidationRawDataAction extends Action
{
    use InteractsWithQueue, Queueable;

    public $showOnDetail = true;

    public $showOnIndex = false;

    public $name = 'UPLOAD GPX/KML/GEOJSON';

    public $HR;

    public function __construct($HR = null)
    {
        $this->HR = HikingRoute::find($HR);
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
        $model = $models->first();

        if ($model->osm2cai_status > 3) {
            return Action::danger('To upload the detected track of the route, the route must have a registration status less than or equal to 3; if necessary proceed first with REVERT VALIDATION');
        }

        if (! $this->checkUserAuthorization($model)) {
            return Action::danger('You are not authorized to perform this action');
        }

        return $this->processGeometryUpload($fields, $model);
    }

    private function checkUserAuthorization($model)
    {
        $user = auth()->user();
        $roles = $user->getRoleNames()->toArray();

        if (in_array('Administrator', $roles) || in_array('National Referent', $roles)) {
            return true;
        }

        if (in_array('Regional Referent', $roles)) {
            return $model->regions->pluck('id')->contains($user->region->id);
        }

        if (in_array('Local Referent', $roles)) {
            return ! $model->sectors->intersect($user->sectors)->isEmpty() ||
                ! $model->areas->intersect($user->areas)->isEmpty() ||
                ! $model->provinces->intersect($user->provinces)->isEmpty();
        }

        return false;
    }

    private function processGeometryUpload($fields, $model)
    {
        if (! $fields->geometry) {
            return Action::danger('Unable to update geometry. Please enter a valid file.');
        }

        $path = $fields->geometry->storeAs(
            'local',
            explode('.', $fields->geometry->hashName())[0].'.'.$fields->geometry->getClientOriginalExtension()
        );

        $content = Storage::get($path);
        $jsonDecoded = json_decode($content, true);
        if (isset($jsonDecoded['features']) && count($jsonDecoded['features']) < 1) {
            return Action::danger('Unable to update geometry. The uploaded file does not contain a valid geometry.');
        }
        $geom = $model->fileToGeometry($content);

        $model->geometry_raw_data = $geom;
        $model->save();

        return Action::message('File uploaded and geometry updated successfully!');
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $confirmText = 'WARNING: the file that will be uploaded will be used exclusively to be compared with the track present in the Cadastre/OpenStreetMap; in case of validation, the Cadastre/OpenStreetMap track (blue on the map) will be validated.';

        return [
            File::make('Geometry')
                ->help($confirmText),
        ];
    }
}
