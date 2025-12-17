<?php

namespace App\Nova\Actions;

use App\Jobs\GeneratePdfJob;
use App\Models\HikingRoute;
use App\Models\TrailSurvey;
use Carbon\Carbon;
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

        // Validate that start date is required
        if (! $fields->start_date) {
            return Action::danger(__('The start date is required'));
        }

        // If end_date is not provided, set it equal to start_date
        $endDate = $fields->end_date ?? $fields->start_date;

        // Convert to Carbon instances for proper comparison
        try {
            $startDate = Carbon::parse($fields->start_date)->startOfDay();
            $endDateParsed = Carbon::parse($endDate)->startOfDay();
        } catch (\Exception $e) {
            return Action::danger(__('Invalid date format'));
        }

        // Validate date relationship - end date must be >= start date
        if ($endDateParsed->lt($startDate)) {
            return Action::danger(__('The end date cannot be earlier than the start date'));
        }

        // Create the TrailSurvey
        $trailSurvey = TrailSurvey::create([
            'hiking_route_id' => $hikingRoute->id,
            'owner_id' => $user->id,
            'start_date' => $fields->start_date,
            'end_date' => $endDate,
            'description' => $fields->description ?? null,
        ]);

        // Get the UgcPoi and UgcTrack with buffer
        $ugcPois = $hikingRoute->getUgcPoisWithBuffer(10, $fields->start_date, $endDate);
        $ugcTracks = $hikingRoute->getUgcTracksWithBuffer(10, $fields->start_date, $endDate);

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
            return Action::redirect(Nova::url('/resources/trail-surveys/'.$trailSurvey->id))
                ->withMessage(__('A new Trail Survey object has been created and the PDF generation has been started. You will be redirected to the object page.'));
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
                ->help(__('Date of start of the survey'))
                ->rules([
                    'required',
                    'date',
                    function ($attribute, $value, $fail) use ($request) {
                        $endDate = $request->input('end_date');
                        if ($endDate && $value) {
                            try {
                                $start = Carbon::parse($value)->startOfDay();
                                $end = Carbon::parse($endDate)->startOfDay();
                                if ($start->gt($end)) {
                                    $fail(__('The start date cannot be later than the end date'));
                                }
                            } catch (\Exception $e) {
                                // Ignore parsing errors, handled by date rule
                            }
                        }
                    },
                ]),

            Date::make(__('End Date'), 'end_date')
                ->nullable()
                ->help(__('Date of end of the survey. If not provided, it will be set equal to the start date.'))
                ->rules([
                    'nullable',
                    'date',
                    function ($attribute, $value, $fail) use ($request) {
                        $startDate = $request->input('start_date');
                        if ($startDate && $value) {
                            try {
                                $start = Carbon::parse($startDate)->startOfDay();
                                $end = Carbon::parse($value)->startOfDay();
                                if ($end->lt($start)) {
                                    $fail(__('The end date cannot be earlier than the start date'));
                                }
                            } catch (\Exception $e) {
                                // Ignore parsing errors, handled by date rule
                            }
                        }
                    },
                ]),
        ];
    }
}
