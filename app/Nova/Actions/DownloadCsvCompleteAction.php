<?php

namespace App\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;

class DownloadCsvCompleteAction extends Action
{
    use InteractsWithQueue, Queueable;

    public function __construct()
    {
        $this->name = __('Download routes csv');
    }

    public $showOnDetail = true;

    public $showOnIndex = false;

    public $showOnTableRow = true;

    public $withoutConfirmation = true;

    /**
     * Perform the action on the given models.
     *
     * @param ActionFields $fields
     * @param Collection $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $model = $models->first();
        $type = strtolower(last(explode('\\', get_class($model))));
        $id = $model->id;
        $name = $model->name;

        return Action::redirect(url('api/csv/'.$type.'/'.$id));
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields($request)
    {
        return [];
    }
}
