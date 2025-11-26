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
            // Carica la relazione owner se non è già caricata
            if (!$trailSurvey->relationLoaded('owner')) {
                $trailSurvey->load('owner');
            }

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
            $trailSurvey->updateQuietly(['pdf_url' => $pdfUrl]);

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
    public function getPdfPath(TrailSurvey $trailSurvey): string
    {
        // Ottieni il nome dell'owner e sanitizzalo per il nome file
        $ownerName = $trailSurvey->owner ? $this->sanitizeFileName($trailSurvey->owner->name) : 'unknown';

        // Formatta le date
        $startDate = $trailSurvey->start_date ? $trailSurvey->start_date->format('Ymd') : 'nodate';
        $endDate = $trailSurvey->end_date ? $trailSurvey->end_date->format('Ymd') : 'nodate';

        return "trail-surveys/{$trailSurvey->id}/survey_{$ownerName}_{$startDate}_{$endDate}.pdf";
    }

    /**
     * Sanitizza una stringa per essere usata come nome file
     */
    protected function sanitizeFileName(string $name): string
    {
        // Rimuovi caratteri speciali e sostituisci spazi con underscore
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        // Rimuovi underscore multipli
        $sanitized = preg_replace('/_+/', '_', $sanitized);
        // Rimuovi underscore all'inizio e alla fine
        return trim($sanitized, '_');
    }
}
