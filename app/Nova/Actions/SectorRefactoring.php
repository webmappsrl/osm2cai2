<?php

namespace App\Nova\Actions;

use Carbon\Carbon;
use App\Models\Sector;
use Illuminate\Bus\Queueable;
use Laravel\Nova\Actions\Action;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\ActionFields;
use App\Jobs\CalculateIntersectionsJob;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class SectorRefactoring extends Action
{
    use InteractsWithQueue, Queueable;

    public $name;

    public function __construct()
    {
        $this->name = __('SECTOR REFACTORING');
    }

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $user = auth()->user();

        if (!$user && $user == null)
            return Action::danger(__('User info is not available'));

        $model = $models->first();
        //if the model has no geometry, we can't refactor the sectors
        if (!$model->geometry)
            return Action::danger(__('This Hiking Route has no geometry'));
        //if the user doesn't have permissions on the model, we can't refactor the sectors
        if (!$user->canManageHikingRoute($model))
            return Action::danger(__('You don\'t have permissions on this Hiking Route'));
        CalculateIntersectionsJob::dispatch($model, Sector::class)->onQueue('geometric-computations');

        return Action::message(__('Sector refactoring job dispatched'));
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
