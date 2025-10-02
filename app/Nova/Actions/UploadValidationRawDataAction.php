<?php

namespace App\Nova\Actions;

use App\Models\HikingRoute;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
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

    public $name;

    public $HR;

    public function __construct($HR = null)
    {
        $this->HR = HikingRoute::find($HR);
        $this->name = __('Upload GPX/KML/GEOJSON');
    }

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $model = $models->first();

        if ($model->osm2cai_status > 3) {
            return Action::danger(__('To upload the detected track of the route, the route must have a registration status less than or equal to 3; if necessary proceed first with REVERT VALIDATION'));
        }

        if (! auth()->user()->canManageHikingRoute($model)) {
            return Action::danger(__('You are not authorized to perform this action'));
        }

        return $this->processGeometryUpload($fields, $model);
    }

    private function processGeometryUpload($fields, $model)
    {
        if (! $fields->geometry) {
            return Action::danger(__('Unable to update geometry. Please enter a valid file.'));
        }

        $file = $fields->geometry;
        $extension = $file->getClientOriginalExtension();
        $allowedExtensions = ['gpx', 'kml', 'geojson'];

        if (! in_array(strtolower($extension), $allowedExtensions)) {
            return Action::danger(__('Invalid file type. Please upload a GPX, KML, or GeoJSON file.'));
        }

        $path = $file->storeAs(
            'local',
            $file->hashName()
        );

        try {
            $content = Storage::disk('local')->get($path);
            $geom = $model->fileToGeometry($content);

            if (! $geom) {
                Storage::disk('local')->delete($path);

                return Action::danger(__('Unable to update geometry. The uploaded file does not contain a valid geometry or could not be processed.'));
            }

            $model->geometry_raw_data = $geom;
            $model->save();

            return Action::message(__('File uploaded and geometry updated successfully!'));
        } catch (\Exception $e) {
            Log::error("Error processing geometry upload for HikingRoute ID {$model->id}: ".$e->getMessage());
            Storage::disk('local')->delete($path);

            return Action::danger(__('An error occurred while processing the file. Please check the logs.'));
        }
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
            File::make('Geometry', 'geometry')
                ->help($confirmText)
                ->acceptedTypes('.gpx,.kml,.geojson'),
        ];
    }
}
