<?php

namespace App\Nova\Actions;

use App\Jobs\SyncClubHikingRouteRelationJob;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class FindClubHrAssociationAction extends Action
{
    use InteractsWithQueue;
    use Queueable;

    public $name = 'Find Club Hiking Route Association';

    /**
     * Indicates if this action can be run without any models.
     *
     * @var bool
     */
    public $standalone = false;

    /**
     * Indicates if this action is only available on the resource detail view.
     *
     * @var bool
     */
    public $onlyOnDetail = true;

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        if ($models->count() !== 1) {
            return Action::danger(__('This action can only be executed on one resource at a time.'));
        }

        $model = $models->first();
        $modelType = class_basename(get_class($model));

        // Dispatch the job to associate clubs to hiking routes
        SyncClubHikingRouteRelationJob::dispatch($modelType, $model->id);

        return Action::message(__('Association process has been started for this :modelType', ['modelType' => $modelType]));
    }

    /**
     * Get the fields available on the action.
     *
     * @return array<int, \Laravel\Nova\Fields\Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [];
    }
}
