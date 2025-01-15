<?php

namespace App\Nova\Actions;

use App\Enums\IssuesStatusEnum;
use App\Enums\IssueStatus;
use App\Models\HikingRoute;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;

class CreateIssue extends Action
{
    use InteractsWithQueue, Queueable;

    public $model;

    public function __construct($model = null)
    {
        $this->model = $model;

        if (! is_null($resourceId = request('resourceId'))) {
            $this->model = HikingRoute::find($resourceId);
        }

        $this->name = __('ACCESSIBILITY ISSUE');
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
        //set the user to the current logged in user
        $user = User::find(auth()->user()->id);
        foreach ($models as $hikingRoute) {
            $hikingRoute->issues_status = $fields->issues_status ?? $hikingRoute->issues_status;
            $hikingRoute->issues_description = $fields->issues_description;
            //set the date field to the current date time when the action is performed
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
