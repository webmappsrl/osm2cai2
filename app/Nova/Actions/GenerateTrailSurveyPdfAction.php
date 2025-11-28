<?php

namespace App\Nova\Actions;

use App\Jobs\GeneratePdfJob;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class GenerateTrailSurveyPdfAction extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = 'Genera PDF';

    /**
     * Indicates if this action is only available on the resource detail view.
     *
     * @var bool
     */
    public $onlyOnDetail = true;

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        foreach ($models as $trailSurvey) {
            GeneratePdfJob::dispatch($trailSurvey);
        }

        return Action::message('Generazione PDF avviata per ' . $models->count() . ' Trail Survey');
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [];
    }
}
