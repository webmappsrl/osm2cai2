<?php

namespace App\Nova\Actions;

use App\Models\Club;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Outl1ne\MultiselectField\Multiselect;

class RemoveMembersFromClub extends Action
{
    use InteractsWithQueue, Queueable;

    public $name;

    public function __construct()
    {
        $this->name = __('Remove members from club');
    }

    /**
     * Esegue l'azione sui modelli selezionati.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $currentUser = auth()->user();

        foreach ($models as $model) {
            if (! $currentUser->canManageClub($model)) {
                return Action::danger(__('You are not authorized to modify this club'));
            }

            $ids = json_decode($fields->members);

            foreach ($ids as $id) {
                $user = User::find($id);
                if ($user && $user->club_id == $model->id) {
                    $user->club_id = null;
                    $user->saveQuietly();
                }
            }
        }

        return Action::message(__('Members removed from the club'));
    }

    /**
     * Define i campi disponibili per l'azione.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        // Se lo slug del club è disponibile (azione eseguita dalla pagina di dettaglio),
        // otteniamo la lista dei membri associati a quel club;
        // altrimenti, come fallback, mostriamo tutti gli utenti che hanno un club associato.
        if ($request->resourceId) {
            $club = Club::find($request->resourceId);
            $users = $club ? $club->users()->orderBy('name')->pluck('name', 'id') : collect();
        } else {
            $users = collect();
        }

        return [
            Multiselect::make(__('Members'), 'members')->options($users),
        ];
    }
}
