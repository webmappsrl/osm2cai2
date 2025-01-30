<?php

namespace App\Nova\Actions;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;

class AssignClubManager extends Action
{
    use InteractsWithQueue, Queueable;

    public $name;

    public function __construct()
    {
        $this->name = __('Assign club\'s manager');
    }

    /**
     * Perform the action on the given models.
     *
     * @param  ActionFields  $fields
     * @param  Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $user = auth()->user();
        foreach ($models as $model) {
            if (! $user->canManageClub()) {
                return Action::danger(__('You are not authorized to modify this club'));
            }
            $user = User::find($fields->clubManager);
            $user->managed_club_id = $model->id;
            $user->club_manager_expire_date = $fields->club_manager_expire_date;
            $user->save();
        }

        return Action::message(__('Club\'s manager assigned successfully'));
    }


    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            Select::make(__('Club\'s manager'), 'clubManager')
                ->options(User::all('id', 'name')->pluck('name', 'id'))
                ->searchable(),
            Date::make(__('Data di scadenza dell\'incarico'), 'club_manager_expire_date')
                ->nullable(),
        ];
    }
}
