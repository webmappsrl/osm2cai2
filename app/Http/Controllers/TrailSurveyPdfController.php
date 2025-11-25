<?php

namespace App\Http\Controllers;

use App\Models\TrailSurvey;
use Dompdf\Dompdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;

class TrailSurveyPdfController extends Controller
{
    /**
     * Genera il contenuto PDF per un TrailSurvey
     */
    public function generatePdfContent(TrailSurvey $trailSurvey): ?string
    {
        try {
            // Carica le relazioni necessarie
            $trailSurvey->load(['hikingRoute', 'owner', 'ugcPois', 'ugcTracks']);

            // Genera l'HTML dalla view
            $html = View::make('trail-survey.pdf', [
                'trailSurvey' => $trailSurvey,
            ])->render();

            // Configura le opzioni per DomPDF (versione 2.0)
            $options = [
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'enableLocalFileAccess' => true,
            ];

            // Crea l'istanza DomPDF con le opzioni
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            return $dompdf->output();
        } catch (\Exception $e) {
            \Log::error("Errore nella generazione PDF: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Download del PDF (opzionale, per accesso diretto)
     */
    public function download(TrailSurvey $trailSurvey): Response
    {
        $pdfContent = $this->generatePdfContent($trailSurvey);

        if (!$pdfContent) {
            abort(500, 'Errore nella generazione del PDF');
        }

        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="trail_survey_' . $trailSurvey->id . '.pdf"');
    }
}

