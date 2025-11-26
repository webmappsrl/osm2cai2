<?php

namespace App\Observers;

use App\Models\TrailSurvey;
use App\Services\TrailSurveyPdfService;
use Illuminate\Support\Facades\Log;

class TrailSurveyObserver
{
    protected TrailSurveyPdfService $pdfService;

    public function __construct(TrailSurveyPdfService $pdfService)
    {
        $this->pdfService = $pdfService;
    }

    /**
     * Handle the TrailSurvey "created" event.
     */
    public function created(TrailSurvey $trailSurvey): void
    {
        Log::info("TrailSurvey {$trailSurvey->id} creato, generazione PDF");
        $this->pdfService->generateAndSavePdf($trailSurvey);
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
            Log::info("TrailSurvey {$trailSurvey->id} aggiornato, generazione PDF");
            $this->pdfService->generateAndSavePdf($trailSurvey);
        }
    }
}
