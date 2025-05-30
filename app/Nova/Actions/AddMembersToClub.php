<?php

namespace App\Nova\Actions;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Outl1ne\MultiselectField\Multiselect;

class AddMembersToClub extends Action
{
    use InteractsWithQueue, Queueable;

    public $name;

    public function __construct()
    {
        $this->name = __('Add members to club');
    }

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $user = auth()->user();
        foreach ($models as $model) {
            if (! $user->canManageClub($model)) {
                return Action::danger(__('You are not authorized to modify this club'));
            }
            $ids = json_decode($fields->members);
            foreach ($ids as $id) {
                $user = User::find($id);
                if ($user) {
                    $user->club_id = $model->id;
                    $user->saveQuietly();
                }
            }
        }

        return Action::message(__('Members added to the club'));
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $users = User::orderBy('name')->pluck('name', 'id');

        return [Multiselect::make('Members')->options($users)];
    }
}
