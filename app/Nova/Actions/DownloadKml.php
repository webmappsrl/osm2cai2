<?php

namespace App\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class DownloadKml extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = 'Download KML';

    public $showOnDetail = true;

    public $showOnIndex = false;

    public $showOnTableRow = true;

    public $withoutConfirmation = true;

    /**
     * Perform the action on the given models.
     *
     * @param  ActionFields  $fields
     * @param  Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $modelType = $models->first()->getMorphClass();

        //trim app\models
        $modelType = str_replace('App\Models\\', '', $modelType);

        return Action::redirect(url('/api/kml/'.$modelType.'/'.$models->first()->id));
    }

    /**
     * Get the fields available on the action.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [];
    }
}
