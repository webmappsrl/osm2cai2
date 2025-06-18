<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Jobs\GetTaxonomyWheresFromOsmfeaturesJob;

class GetTaxonomyWheresFromOsmfeaturesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:get-taxonomy-wheres-from-osmfeatures';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command dispatch jobs to get taxonomy wheres from osmfeatures';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fetching hiking routes with geometry...');
        $hikingRoutes = HikingRoute::whereNotNull('geometry')->get(['id', 'geometry', 'properties']);

        $total = $hikingRoutes->count();
        $this->info("Found {$total} hiking routes to process.");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($hikingRoutes as $route) {
            dispatch(new GetTaxonomyWheresFromOsmfeaturesJob($route))->onQueue('geometric-computations');
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Dispatching complete. {$total} jobs dispatched.");
    }
}
