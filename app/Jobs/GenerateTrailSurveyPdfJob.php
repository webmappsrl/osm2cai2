<?php

namespace App\Jobs;

use App\Models\TrailSurvey;
use App\Services\TrailSurveyPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateTrailSurveyPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public TrailSurvey $trailSurvey;

    /**
     * Create a new job instance.
     */
    public function __construct(TrailSurvey $trailSurvey)
    {
        $this->trailSurvey = $trailSurvey;
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(TrailSurveyPdfService $pdfService): void
    {
        Log::info("Inizio generazione PDF per TrailSurvey {$this->trailSurvey->id}");

        $pdfUrl = $pdfService->generateAndSavePdf($this->trailSurvey);
        $this->trailSurvey->update(['pdf_url' => $pdfUrl]);
        if ($pdfUrl) {
            Log::info("PDF generato con successo per TrailSurvey {$this->trailSurvey->id}", [
                'pdf_url' => $pdfUrl,
            ]);
        } else {
            Log::error("Errore nella generazione PDF per TrailSurvey {$this->trailSurvey->id}");
        }
    }
}
