<?php

namespace App\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class DownloadShape extends Action
{
    use InteractsWithQueue, Queueable;

    public $name;

    public $showOnDetail = true;

    public $showOnIndex = false;

    public $showOnTableRow = true;

    public $withoutConfirmation = true;

    public function __construct()
    {
        $this->name = __('Download Shape');
    }

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $modelType = $models->first()->getMorphClass();

        // trim app\models
        $modelType = str_replace('App\Models\\', '', $modelType);

        foreach ($models as $model) {
            return Action::redirect(url('/api/shapefile/'.$modelType.'/'.$model->id));
        }
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
