<?php

namespace App\Nova\Actions;

use App\Enums\UserRole;
use App\Models\SignageProject;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;

/**
 * Action per aggiungere HikingRoute selezionate a un SignageProject
 */
class AddHikingRoutesToSignageProject extends Action
{
    use InteractsWithQueue, Queueable;

    public $name;

    public function __construct()
    {
        $this->name = __('Aggiungi a Progetto Segnaletica');
    }

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $user = Auth::user();

        if (! $user) {
            return Action::danger(__('Utente non autenticato'));
        }

        $signageProjectId = $fields->get('signage_project');
        $signageProject = SignageProject::find($signageProjectId);

        if (! $signageProject) {
            return Action::danger(__('Progetto Segnaletica non trovato'));
        }

        // Verifica che l'utente sia il proprietario del progetto o un admin
        if ($signageProject->user_id !== $user->id) {
            return Action::danger(__('Non hai i permessi per aggiungere percorsi a questo progetto. Solo il creatore del progetto o un amministratore possono aggiungere hiking routes.'));
        }

        $addedCount = 0;
        foreach ($models as $hikingRoute) {
            if (! $signageProject->hikingRoutes->contains($hikingRoute->id)) {
                $signageProject->hikingRoutes()->attach($hikingRoute->id);
                $addedCount++;
            }
        }

        if ($addedCount > 0) {
            return Action::message(__(':count percorsi aggiunti al progetto ":project"', [
                'count' => $addedCount,
                'project' => $signageProject->getStringName(),
            ]));
        }

        return Action::message(__('Tutti i percorsi selezionati sono già presenti nel progetto'));
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields($request)
    {
        return [
            Select::make(__('Progetto Segnaletica'), 'signage_project')
                ->options(function () {
                    $user = Auth::user();

                    if (! $user) {
                        return [];
                    }

                    // Gli admin vedono tutti i progetti, gli altri utenti solo i propri
                    $query = SignageProject::query();
                    $query->where('user_id', $user->id);

                    return $query->get()
                        ->mapWithKeys(function ($project) {
                            $name = $project->getStringName();
                            return [$project->id => $name ?: 'Progetto #' . $project->id];
                        })
                        ->sort()
                        ->toArray();
                })
                ->searchable()
                ->rules('required')
                ->help(function () {
                    $user = Auth::user();
                    if ($user) {
                        return __('Seleziona il progetto segnaletica a cui aggiungere i percorsi. Vedi tutti i progetti.');
                    }
                    return __('Seleziona il progetto segnaletica a cui aggiungere i percorsi. Vedi solo i progetti che hai creato.');
                }),
        ];
    }
}
