<?php

namespace App\Services;

use App\Traits\GeneratesPdfTrait;
use Dompdf\Dompdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Wm\WmPackage\Services\StorageService;

class PdfService
{
    protected StorageService $storageService;

    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Generate and save the PDF for a model that uses GeneratesPdfTrait
     */
    public function generateAndSavePdf(Model $model): ?string
    {
        // Verify that the model uses the trait
        if (! in_array(GeneratesPdfTrait::class, class_uses_recursive($model))) {
            Log::error('Il modello '.get_class($model).' non usa il trait GeneratesPdfTrait');

            return null;
        }

        try {
            // Load the necessary relations
            $relations = $model->getPdfRelationsToLoad();
            if (! empty($relations)) {
                foreach ($relations as $relation) {
                    if (! $model->relationLoaded($relation)) {
                        $model->load($relation);
                    }
                }
            }

            // Generate the PDF content
            $pdfContent = $this->generatePdfContent($model);

            if (! $pdfContent) {
                Log::error('Errore nella generazione del PDF per '.get_class($model)." {$model->id}");

                return null;
            }

            // Generate the path for the PDF
            $path = $model->getPdfPath();

            // Save the PDF using StorageService
            $savedPath = $this->storageService->getPublicDisk()->put($path, $pdfContent);

            if (! $savedPath) {
                Log::error('Errore nel salvataggio del PDF per '.get_class($model)." {$model->id}");

                return null;
            }

            // Get the public URL of the PDF
            $publicDisk = $this->storageService->getPublicDisk();
            $cleanPath = ltrim($path, '/');
            $pdfUrl = url('/storage/'.$cleanPath);

            // Note: pdf_url is not saved to database, it's generated dynamically from the path
            // The URL is generated on-the-fly when needed (e.g., in Nova resources)

            Log::info('PDF generato con successo per '.get_class($model)." {$model->id}", [
                'pdf_url' => $pdfUrl,
                'path' => $path,
            ]);

            return $pdfUrl;
        } catch (\Exception $e) {
            Log::error('Errore nella generazione PDF per '.get_class($model)." {$model->id}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Generate the binary PDF content
     */
    protected function generatePdfContent(Model $model): ?string
    {
        try {
            // Reload the model from the database to ensure we have the latest data
            $freshModel = $model->fresh();

            if (! $freshModel) {
                Log::error(get_class($model).' non trovato nel database');

                return null;
            }

            $model = $freshModel;

            // Check if a custom controller exists
            $controllerClass = $model->getPdfControllerClass();
            if ($controllerClass && class_exists($controllerClass)) {
                $controller = app($controllerClass);
                if (method_exists($controller, 'generatePdfContent')) {
                    return $controller->generatePdfContent($model);
                }
            }

            // Standard generation: load relations and generate HTML from the view
            $relations = $model->getPdfRelationsToLoad();
            if (! empty($relations)) {
                $model->load($relations);
            }

            // Generate HTML from the view
            $viewName = $model->getPdfViewName();
            $variableName = $model->getPdfViewVariableName();

            $html = View::make($viewName, [
                $variableName => $model,
            ])->render();

            // Configure options for DomPDF
            $options = [
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
            ];

            // Create the DomPDF instance
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            return $dompdf->output();
        } catch (\Exception $e) {
            Log::error('Errore nella generazione PDF: '.$e->getMessage());

            return null;
        }
    }
}
