<?php

namespace App\Http\Controllers;

use App\Models\TrailSurvey;
use Dompdf\Dompdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class TrailSurveyPdfController extends Controller
{
    /**
     * Genera il contenuto PDF per un TrailSurvey
     */
    public function generatePdfContent(TrailSurvey $trailSurvey): ?string
    {
        try {
            // Ricarica il modello dal database per assicurarsi di avere i dati più recenti
            $trailSurvey = $trailSurvey->fresh();

            if (! $trailSurvey) {
                Log::error('TrailSurvey non trovato nel database');

                return null;
            }

            // Carica le relazioni necessarie
            $trailSurvey->load(['hikingRoute', 'owner', 'ugcPois', 'ugcTracks']);

            // Log per debug
            Log::info("Generazione PDF per TrailSurvey {$trailSurvey->id}", [
                'description' => $trailSurvey->description,
                'description_length' => $trailSurvey->description ? strlen($trailSurvey->description) : 0,
            ]);

            // Genera l'HTML dalla view
            $html = View::make('trail-survey.pdf', [
                'trailSurvey' => $trailSurvey,
            ])->render();

            // Configura le opzioni per DomPDF (DomPDF 2.0 accetta array di opzioni)
            $options = [
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'chroot' => storage_path('app/public'),
            ];

            // Crea l'istanza DomPDF
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');

            try {
                $dompdf->render();
            } catch (\Exception $e) {
                Log::error('Errore nel rendering DomPDF: ' . $e->getMessage(), [
                    'trail_survey_id' => $trailSurvey->id,
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }

            return $dompdf->output();
        } catch (\Exception $e) {
            Log::error('Errore nella generazione PDF: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Download del PDF (opzionale, per accesso diretto)
     */
    public function download(TrailSurvey $trailSurvey): Response
    {
        $pdfContent = $this->generatePdfContent($trailSurvey);

        if (! $pdfContent) {
            abort(500, 'Errore nella generazione del PDF');
        }

        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="trail_survey_' . $trailSurvey->id . '.pdf"');
    }
}
