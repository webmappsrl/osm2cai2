<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Wm\WmPackage\Models\App;
use Wm\WmPackage\Models\TaxonomyActivity;

class AssignHikingTaxonomyToAppTracksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:assign-hiking-taxonomy-to-hiking-routes
                            {--dry-run : Esegui senza modificare il database}
                            {--force : Forza l\'operazione anche se esistono già associazioni}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assegna la TaxonomyActivity con identifier "hiking" a tutte le HikingRoutes dell\'app osm2cai';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');

        if ($isDryRun) {
            $this->warn('🧪 Modalità DRY RUN attivata');
        }

        // Verifica che l'app esista
        $app = App::where('sku', 'it.webmapp.osm2cai')->first();
        if (! $app) {
            $this->error('❌ App osm2cai non trovata!');

            return 1;
        }

        // Verifica che la taxonomy activity con identifier "hiking" esista
        $hikingTaxonomy = TaxonomyActivity::where('identifier', 'hiking')->first();
        if (! $hikingTaxonomy) {
            $this->error("❌ TaxonomyActivity 'hiking' non trovata!");

            return 1;
        }

        // Conta le hiking routes dell'app
        $totalCount = HikingRoute::where('app_id', $app->id)->count();

        if ($totalCount === 0) {
            $this->warn("⚠️  Nessuna hiking route trovata per l'app osm2cai");

            return 0;
        }

        if ($isDryRun) {
            $this->info("🧪 [DRY RUN] Avrei associato {$totalCount} hiking routes alla taxonomy 'hiking'");

            return 0;
        }

        $attached = 0;
        $skipped = 0;

        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        // Disattiva gli eventi Eloquent durante tutta l’operazione
        Model::withoutEvents(function () use ($app, $hikingTaxonomy, &$attached, &$skipped, $bar, $force) {
            HikingRoute::where('app_id', $app->id)
                ->chunk(100, function ($hikingRoutes) use ($hikingTaxonomy, &$attached, &$skipped, $bar, $force) {
                    foreach ($hikingRoutes as $hikingRoute) {
                        $alreadyAttached = $hikingRoute->taxonomyActivities()
                            ->where('taxonomy_activity_id', $hikingTaxonomy->id)
                            ->exists();

                        if (! $alreadyAttached || $force) {
                            // Se force è attivo, si rimuove l’associazione precedente
                            if ($force && $alreadyAttached) {
                                $hikingRoute->taxonomyActivities()->detach($hikingTaxonomy->id);
                            }

                            $hikingRoute->taxonomyActivities()->attach($hikingTaxonomy->id, [
                                'duration_forward' => 0,
                                'duration_backward' => 0,
                            ]);

                            $attached++;
                        } else {
                            $skipped++;
                        }

                        $bar->advance();
                    }
                });
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Associate {$attached} hiking routes alla taxonomy 'hiking'");
        if ($skipped > 0) {
            $this->info("⏭️  Saltate {$skipped} associazioni già esistenti");
        }

        return 0;
    }
}
