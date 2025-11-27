<?php

namespace App\Console\Commands;

use App\Jobs\GeneratePdfJob;
use App\Models\TrailSurvey;
use Illuminate\Console\Command;

class GenerateTrailSurveyPdfCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trail-survey:generate-pdf {id? : ID del TrailSurvey (opzionale)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera il PDF per uno o tutti i TrailSurvey';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $id = $this->argument('id');

        if ($id) {
            $trailSurvey = TrailSurvey::find($id);
            if (!$trailSurvey) {
                $this->error("TrailSurvey con ID {$id} non trovato");
                return 1;
            }

            $this->info("Generazione PDF per TrailSurvey {$id}...");
            GeneratePdfJob::dispatch($trailSurvey);
            $this->info("Job di generazione PDF avviato per TrailSurvey {$id}");
        } else {
            $this->info("Generazione PDF per tutti i TrailSurvey...");
            $count = 0;
            TrailSurvey::chunk(100, function ($trailSurveys) use (&$count) {
                foreach ($trailSurveys as $trailSurvey) {
                    GeneratePdfJob::dispatch($trailSurvey);
                    $count++;
                }
            });

            $this->info("Job di generazione PDF avviati per {$count} TrailSurvey");
        }

        return 0;
    }
}
