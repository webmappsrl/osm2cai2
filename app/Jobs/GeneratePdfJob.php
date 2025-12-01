<?php

namespace App\Jobs;

use App\Services\PdfService;
use App\Traits\GeneratesPdfTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Generic job for generating PDF for models that use GeneratesPdfTrait
 */
class GeneratePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Model $model;

    /**
     * Create a new job instance.
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(PdfService $pdfService): void
    {
        // Verify that the model uses the trait
        if (! in_array(GeneratesPdfTrait::class, class_uses_recursive($this->model))) {
            Log::error('Il modello '.get_class($this->model).' non usa il trait GeneratesPdfTrait', [
                'model_id' => $this->model->id,
                'model_class' => get_class($this->model),
            ]);

            return;
        }

        $modelClass = get_class($this->model);
        Log::info("Inizio generazione PDF per {$modelClass} {$this->model->id}");

        $pdfUrl = $pdfService->generateAndSavePdf($this->model);

        if ($pdfUrl) {
            Log::info("PDF generato con successo per {$modelClass} {$this->model->id}", [
                'pdf_url' => $pdfUrl,
            ]);
        } else {
            Log::error("Errore nella generazione PDF per {$modelClass} {$this->model->id}");
        }
    }
}
