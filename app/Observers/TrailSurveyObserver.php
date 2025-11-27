<?php

namespace App\Observers;

use App\Jobs\GeneratePdfJob;
use App\Models\TrailSurvey;
use Illuminate\Support\Facades\Log;

class TrailSurveyObserver
{

    public function __construct() {}



    /**
     * Handle the TrailSurvey "updated" event. Generate the PDF if relevant fields have changed
     */
    public function updated(TrailSurvey $trailSurvey): void
    {
        // Generate the PDF if relevant fields have changed
        $relevantFields = ['hiking_route_id', 'start_date', 'end_date', 'description'];

        if ($trailSurvey->wasChanged($relevantFields)) {
            Log::info("TrailSurvey {$trailSurvey->id} aggiornato, generazione PDF");
            GeneratePdfJob::dispatchSync($trailSurvey);
        }
    }
}
