<?php

namespace App\Nova\Actions;

use App\Models\Area;
use App\Models\User;
use App\Models\Province;
use Illuminate\Bus\Queueable;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Actions\Action;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\BelongsToMany;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class SectorAssignModerator extends Action
{
    use InteractsWithQueue, Queueable;

    public $showOnDetail = true;
    public $showOnIndex = false;

    public function __construct()
    {
        $this->name = __('Assign Moderator');
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
        foreach ($models as $model) {
            $region = auth()->user()->region()->first();
            if (!$region) {
                return Action::danger(__('Sorry, you are not authorized to perform this action'));
            }
            $sectorsIds = $region->sectorsIds();
            $containsValue = collect($sectorsIds)->contains($model->id);
            if (!$containsValue) {
                return Action::danger(__('Sorry, you are not authorized to perform this action'));
            }
            $model->users()->syncWithoutDetaching([$fields['moderator']]);
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
            Select::make(__('Moderator'))->options(function () {
                return Cache::remember('users', 60, function () {
                    return User::all()->pluck('name', 'id')->toArray();
                });
            })->searchable()
        ];
    }
}
