<?php

namespace App\Nova\Actions;

use Exception;
use Throwable;
use App\Models\HikingRoute;
use Illuminate\Http\Request;
use Illuminate\Bus\Queueable;
use Laravel\Nova\Fields\File;
use Imumz\LeafletMap\LeafletMap;
use Laravel\Nova\Actions\Action;
use App\Services\AreaModelService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class UploadSectorGeometryAction extends Action
{
    use InteractsWithQueue, Queueable;

    public $onlyOnDetail = true;

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function __construct()
    {
        $this->name = __('Aggiorna geometria');
    }

    public function handle(ActionFields $fields, Collection $models)
    {
        $model = $models->first();

        if (!$fields->geometry) {
            return Action::danger(__("Unable to update geometry. Please provide a valid file."));
        }

        $content = $fields->geometry->get();
        $geom = $model->fileToGeometry($content);
        $model->geometry = $geom;

        try {
            DB::transaction(function () use ($model) {
                $model->save(); //triggering intersection computation with hiking routes (because geometry is changed)

                $area = $model->parent;
                $service = app()->make(AreaModelService::class);
                $service->computeAndSaveGeometryBySectors($area);
            });

            return Action::message(__('Geometry updated successfully!'));
        } catch (Throwable $t) {
            Log::error($t->getMessage());
            return Action::danger(__("Unable to update geometry. Something went wrong: " . $t->getMessage()));
        }
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields($request)
    {
        return [
            File::make('Geometry')->help(__('Upload a gpx, kml or geojson file to update the sector geometry and update all technical data of the affected routes (routes in this sector + those within adjacent sectors)'))
        ];
    }
}
