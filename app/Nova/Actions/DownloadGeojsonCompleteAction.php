<?php

namespace App\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Heading;

class DownloadGeojsonCompleteAction extends Action
{
    use InteractsWithQueue, Queueable;

    public function __construct()
    {
        $this->name = __('Download routes geojson');
    }

    public $showOnDetail = true;

    public $showOnIndex = false;

    public $showOnTableRow = true;

    public $withoutConfirmation = false;

    /**
     * Perform the action on the given models.
     *
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $model = $models->first();
        $type = strtolower(last(explode('\\', get_class($model))));
        $id = $model->id;
        $name = $model->name;

        return Action::redirect(url('api/geojson-complete/'.$type.'/'.$id));
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields($request)
    {
        $string = __('File generation may take a few moments. Click "Run Action" to proceed.');
        $heading = <<<HTML
<p><strong>{$string}</strong> </p>
HTML;

        return [
            Heading::make($heading)
                ->asHtml(),
        ];
    }
}
