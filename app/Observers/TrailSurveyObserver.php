<?php

namespace App\Observers;

use App\Jobs\GenerateTrailSurveyPdfJob;
use App\Models\TrailSurvey;
use Illuminate\Support\Facades\Log;

class TrailSurveyObserver
{
    /**
     * Handle the TrailSurvey "created" event.
     */
    public function created(TrailSurvey $trailSurvey): void
    {
        Log::info("TrailSurvey {$trailSurvey->id} creato, dispatch generazione PDF");
        GenerateTrailSurveyPdfJob::dispatch($trailSurvey);
    }

    /**
     * Handle the TrailSurvey "updated" event.
     * Genera il PDF solo se sono cambiati campi rilevanti
     */
    public function updated(TrailSurvey $trailSurvey): void
    {
        // Genera il PDF se sono cambiati campi rilevanti
        $relevantFields = ['hiking_route_id', 'start_date', 'end_date', 'description'];
        
        if ($trailSurvey->wasChanged($relevantFields)) {
            Log::info("TrailSurvey {$trailSurvey->id} aggiornato, dispatch generazione PDF");
            GenerateTrailSurveyPdfJob::dispatch($trailSurvey);
        }
    }
}

