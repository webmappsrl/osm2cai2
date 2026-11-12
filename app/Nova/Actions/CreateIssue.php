<?php

namespace App\Nova\Actions;

use App\Enums\IssuesStatusEnum;
use App\Models\HikingRoute;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Textarea;

class CreateIssue extends Action
{
    use InteractsWithQueue, Queueable;

    public $name;

    public $model;

    public function __construct($model = null)
    {
        $this->model = $model;

        if (! is_null($resourceId = request('resourceId'))) {
            $this->model = HikingRoute::find($resourceId);
        }

        $this->name = __('Accessibility Issue');
    }

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        // set the user to the current logged in user
        $user = User::find(auth()->user()->id);

        // Si aggiornano solo i percorsi nel territorio dell'utente; gli altri vengono saltati e contati (oc:8945)
        [$authorizedRoutes, $skippedRoutes] = $models->partition(
            fn ($hikingRoute) => $user->canManageHikingRoute($hikingRoute)
        );

        foreach ($authorizedRoutes as $hikingRoute) {
            $hikingRoute->issues_status = $fields->issues_status ?? $hikingRoute->issues_status;
            $hikingRoute->issues_description = $fields->issues_description;
            // set the date field to the current date time when the action is performed
            $hikingRoute->issues_last_update = now();
            $hikingRoute->issues_user_id = $user->id ?? $hikingRoute->issues_user_id;
            $hikingRoute->save();
            $chronology = is_string($hikingRoute->issues_chronology) ? json_decode($hikingRoute->issues_chronology, true) : $hikingRoute->issues_chronology;
            $chronology[] = [
                'issues_status' => $hikingRoute->issues_status,
                'issues_description' => $hikingRoute->issues_description,
                'issues_last_update' => now(),
                'issues_user' => $user->name ?? $hikingRoute->issues_user->name,
            ];
            $hikingRoute->issues_chronology = $chronology;
            $hikingRoute->issues_last_update = now();
            $hikingRoute->saveQuietly();
        }

        // Nova chiama handle() una volta per ogni blocco di $chunkCount percorsi: qui si restituiscono
        // solo i conteggi del blocco, il messaggio si sceglie sul totale in handleResult() (oc:8945)
        return [
            'updated' => $authorizedRoutes->count(),
            'skipped' => $skippedRoutes->count(),
        ];
    }

    /**
     * Sceglie il messaggio finale sommando i conteggi di tutti i blocchi.
     *
     * Senza questo override Nova mostrerebbe solo l'esito dell'ultimo blocco (Action::handleResult()).
     *
     * @return mixed
     */
    public function handleResult(ActionFields $fields, array $results)
    {
        // Nova restituisce null per i blocchi senza percorsi
        $counts = array_filter($results, 'is_array');
        $updated = array_sum(array_column($counts, 'updated'));
        $skipped = array_sum(array_column($counts, 'skipped'));

        if ($updated === 0) {
            return Action::danger(__('You are not authorized to report issues on the selected hiking routes'));
        }

        if ($skipped > 0) {
            return Action::message(__('Issues updated on :updated hiking routes, :skipped skipped because outside your territory', [
                'updated' => $updated,
                'skipped' => $skipped,
            ]));
        }

        return null;
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields($request)
    {
        $options = [];

        foreach (IssuesStatusEnum::cases() as $status) {
            $options[$status->value] = $status->value;
        }

        return [
            Select::make(__('Issues Status'), 'issues_status')
                ->options($options)
                ->displayUsingLabels()
                ->rules('required')
                ->default($this->model->issues_status ?? null),
            Textarea::make(__('Issues Description'), 'issues_description')
                ->default($this->model->issues_description ?? null)
                ->nullable(),
        ];
    }
}
