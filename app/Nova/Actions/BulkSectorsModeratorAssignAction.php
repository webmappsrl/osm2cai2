<?php

namespace App\Nova\Actions;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;

class BulkSectorsModeratorAssignAction extends Action
{
    use InteractsWithQueue, Queueable;

    public $name;

    public function __construct()
    {
        $this->name = __('Associa Referente Sentieristica');
    }

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $userId = $fields->get('user');
        $models->map(function ($model) use ($userId) {
            if (! $model->moderators->contains($userId)) {
                $model->moderators()->attach($userId);
            }
        });

        return Action::message(__('Referenti Sentieristica assegnati correttamente!'));
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
            Select::make(__('User'))->searchable()->options(
                $users
            ),
        ];
    }
}
