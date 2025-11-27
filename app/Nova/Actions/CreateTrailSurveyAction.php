<?php

namespace App\Nova\Actions;

use App\Models\HikingRoute;
use App\Models\TrailSurvey;
use App\Jobs\GeneratePdfJob;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;

class CreateTrailSurveyAction extends Action
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

        $this->name = __('Create Trail Survey');
    }

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
        $hikingRoute = $models->first();

        if (! $hikingRoute) {
            return Action::danger(__('Hiking route not found'));
        }

        $user = Auth::user();

        if (! $user) {
            return Action::danger(__('User not authenticated'));
        }

        // Create the TrailSurvey
        $trailSurvey = TrailSurvey::create([
            'hiking_route_id' => $hikingRoute->id,
            'owner_id' => $user->id,
            'start_date' => $fields->start_date,
            'end_date' => $fields->end_date,
            'description' => $fields->description ?? null,
        ]);

        // Get the UgcPoi and UgcTrack with buffer
        $ugcPois = $hikingRoute->getUgcPoisWithBuffer(10, $fields->start_date, $fields->end_date);
        $ugcTracks = $hikingRoute->getUgcTracksWithBuffer(10, $fields->start_date, $fields->end_date);

        // Add the relations
        if ($ugcPois->isNotEmpty()) {
            $trailSurvey->ugcPois()->attach($ugcPois->pluck('id')->toArray());
        }

        if ($ugcTracks->isNotEmpty()) {
            $trailSurvey->ugcTracks()->attach($ugcTracks->pluck('id')->toArray());
        }
        // Generate the PDF synchronously
        GeneratePdfJob::dispatchSync($trailSurvey);
        // Redirect to the detail of the created TrailSurvey
        return Action::redirect(Nova::url('/resources/trail-surveys/' . $trailSurvey->id));
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            Date::make(__('Start Date'), 'start_date')
                ->required()
                ->help(__('Date of start of the survey')),

            Date::make(__('End Date'), 'end_date')
                ->required()
                ->help(__('Date of end of the survey')),
        ];
    }
}
