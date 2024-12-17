<?php

namespace App\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class DownloadFeatureCollection extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = 'Download GeoJSON';

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        //get the name of the class lowercase
        $class = get_class($models[0]);
        $class = class_basename($class);
        $className = strtolower($class);
        $modelIds = [];
        foreach ($models as $model) {
            $modelIds[] = $model->id;
        }

        return Action::redirect(url('api/geojson/' . $className . '/' . implode(',', $modelIds)));
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
