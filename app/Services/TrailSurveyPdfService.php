<?php

namespace App\Services;

use App\Models\TrailSurvey;
use Illuminate\Support\Facades\Log;
use Wm\WmPackage\Services\StorageService;

class TrailSurveyPdfService
{
    protected StorageService $storageService;

    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Genera e salva il PDF per un TrailSurvey
     */
    public function generateAndSavePdf(TrailSurvey $trailSurvey): ?string
    {
        try {
            // Genera il PDF usando il controller
            $pdfController = app(\App\Http\Controllers\TrailSurveyPdfController::class);
            $pdfContent = $pdfController->generatePdfContent($trailSurvey);

            if (!$pdfContent) {
                Log::error("Errore nella generazione del PDF per TrailSurvey {$trailSurvey->id}");
                return null;
            }

            // Genera il path per il PDF
            $path = $this->getPdfPath($trailSurvey);

            // Salva il PDF usando StorageService di wm-package
            $savedPath = $this->storageService->getPublicDisk()->put($path, $pdfContent);

            if (!$savedPath) {
                Log::error("Errore nel salvataggio del PDF per TrailSurvey {$trailSurvey->id}");
                return null;
            }

            // Ottieni l'URL pubblico del PDF
            $pdfUrl = $this->storageService->getPublicDisk()->url($path);

            // Salva l'URL sul modello
            $trailSurvey->update(['pdf_url' => $pdfUrl]);

            Log::info("PDF generato con successo per TrailSurvey {$trailSurvey->id}", [
                'pdf_url' => $pdfUrl,
                'path' => $path,
            ]);

            return $pdfUrl;
        } catch (\Exception $e) {
            Log::error("Errore nella generazione PDF per TrailSurvey {$trailSurvey->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Genera il path per il PDF
     */
    protected function getPdfPath(TrailSurvey $trailSurvey): string
    {
        return "trail-surveys/{$trailSurvey->id}/survey_{$trailSurvey->id}_" . now()->format('YmdHis') . ".pdf";
    }
}

