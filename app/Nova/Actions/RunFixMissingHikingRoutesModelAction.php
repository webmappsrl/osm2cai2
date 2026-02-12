<?php

namespace App\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;

class RunFixMissingHikingRoutesModelAction extends Action
{
    use InteractsWithQueue, Queueable;

    private const ALLOWED_MODELS = ['sectors', 'areas', 'provinces'];

    private string $modelType;

    public function __construct(string $modelType)
    {
        $this->modelType = strtolower($modelType);
        $this->name = __('Fix Missing Hiking Routes :model', ['model' => ucfirst($this->modelType)]);
    }

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        if (! in_array($this->modelType, self::ALLOWED_MODELS, true)) {
            return Action::danger(__('Invalid model type configured for this action.'));
        }

        $exitCode = Artisan::call('osm2cai:fix-missing-hiking-routes-sectors', [
            '--model' => $this->modelType,
        ]);

        if ($exitCode !== 0) {
            return Action::danger(__('Unable to run the command. Check logs for details.'));
        }

        return Action::message(__('Command started for :model.', ['model' => $this->modelType]));
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
