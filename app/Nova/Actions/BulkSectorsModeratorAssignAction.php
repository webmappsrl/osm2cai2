<?php

namespace App\Nova\Actions;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;

class BulkSectorsModeratorAssignAction extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = "Associa moderatore";

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $userId = $fields->get('user');
        $models->map(function ($model) use ($userId) {
            if (! $model->users->contains($userId))
                $model->users()->attach($userId);
        });
        return Action::message('Moderatori assegnati correttamente!');
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields($request)
    {
        $users = User::all(['id', 'email', 'name'])->keyBy('id')->map(function ($e) {
            return "{$e->name} ({$e->email})";
        })->all();
        return [
            Select::make('User')->searchable()->options(
                $users
            )
        ];
    }
}
