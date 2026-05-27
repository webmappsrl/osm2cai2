<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupSiHikingRoutesManualDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:cleanup-si-hiking-routes-manual-data
                            {--dry-run : Run without writing to the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Removes manual_data from SiHikingRoute records (app_id=2, layer_id=6) to restore DEM values as the current value';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $isVerbose = $this->output->isVerbose();

        if ($isDryRun) {
            $this->warn('DRY RUN mode enabled: no database writes will be performed.');
        }

        $query = HikingRoute::query()
            ->where('app_id', 2)
            ->whereIn('id', function ($sub) {
                $sub->select('layerable_id')
                    ->from('layerables')
                    ->where('layer_id', 6)
                    ->where('layerable_type', HikingRoute::class);
            })
            ->whereNotNull('properties')
            ->whereRaw("properties::jsonb ?? 'manual_data'");

        $total = $query->count();

        if ($total === 0) {
            $this->info('No records with manual_data found. Nothing to do.');

            return 0;
        }

        $this->info("Found {$total} records with the manual_data key.");

        $cleaned = 0;
        $skipped = 0;
        $errors = 0;
        $noDemIds = [];

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(100, function ($routes) use (
            $isDryRun,
            $isVerbose,
            $bar,
            &$cleaned,
            &$skipped,
            &$errors,
            &$noDemIds,
        ) {
            foreach ($routes as $route) {
                try {
                    $properties = $route->properties;

                    if (! is_array($properties)) {
                        $skipped++;
                        Log::info('osm2cai:cleanup-si-hiking-routes-manual-data: skip', [
                            'id' => $route->id,
                            'reason' => 'properties is not an array',
                        ]);
                        if ($isVerbose) {
                            $this->newLine();
                            $this->line("  SKIP [{$route->id}] properties is not an array");
                        }
                        $bar->advance();
                        continue;
                    }

                    $manualData = $properties['manual_data'] ?? null;

                    if (empty($manualData)) {
                        $skipped++;
                        Log::info('osm2cai:cleanup-si-hiking-routes-manual-data: skip', [
                            'id' => $route->id,
                            'reason' => 'manual_data is missing or empty',
                        ]);
                        if ($isVerbose) {
                            $this->newLine();
                            $this->line("  SKIP [{$route->id}] manual_data is missing or empty");
                        }
                        $bar->advance();
                        continue;
                    }

                    Log::info('osm2cai:cleanup-si-hiking-routes-manual-data', [
                        'id' => $route->id,
                        'manual_data' => $manualData,
                        'dry_run' => $isDryRun,
                    ]);

                    $demData = $properties['dem_data'] ?? null;
                    $osmData = $properties['osm_data'] ?? null;
                    if (empty($demData) && empty($osmData)) {
                        $noDemIds[] = $route->id;
                    }

                    if (! $isDryRun) {
                        unset($properties['manual_data']);
                        $route->properties = $properties;
                        $route->saveQuietly();
                    }

                    $cleaned++;
                    if ($isVerbose) {
                        $mode = $isDryRun ? 'DRY-RUN' : 'CLEANED';
                        $this->newLine();
                        $this->line("  {$mode} [{$route->id}]");
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    $this->newLine();
                    $this->warn("  ERROR [{$route->id}]: {$e->getMessage()}");
                    Log::error('osm2cai:cleanup-si-hiking-routes-manual-data: record error', [
                        'id' => $route->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Cleaned: {$cleaned}");
        $this->info("Skipped: {$skipped}");

        if ($errors > 0) {
            $this->warn("Errors: {$errors}");
        }

        if (! empty($noDemIds)) {
            $this->newLine();
            $this->warn('The following records were ' . ($isDryRun ? 'identified for cleanup' : 'cleaned') . ' but have neither DEM data nor OSM data available:');
            $this->warn('IDs: ' . implode(', ', $noDemIds));
        }

        return 0;
    }
}
