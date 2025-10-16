<?php

namespace App\Nova\Actions;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;

class SectorAssignModerator extends Action
{
    use InteractsWithQueue, Queueable;

    public $showOnDetail = true;

    public $showOnIndex = false;

    public $name;

    public function __construct()
    {
        $this->name = __('Assign Sector Moderator');
    }

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        foreach ($models as $model) {
            $region = auth()->user()->region()->first();
            if (! $region) {
                return Action::danger(__('Sorry, you are not authorized to perform this action'));
            }
            $sectorsIds = $region->sectorsIds();
            $containsValue = collect($sectorsIds)->contains($model->id);
            if (! $containsValue) {
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
            })->searchable(),
        ];
    }
}
