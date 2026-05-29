<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupSiHikingRoutesManualDataCommand extends Command
{
    protected $signature = 'osm2cai:cleanup-si-hiking-routes-manual-data
                            {--dry-run : Run without writing to the database}';

    protected $description = 'Removes manual_data from HikingRoute records (app_id=2) and restores top-level DEM fields from osm_data/dem_data cascade';

    private const DEM_FIELDS = [
        'ascent', 'descent', 'ele_min', 'ele_max',
        'ele_from', 'ele_to', 'distance',
        'duration_forward', 'duration_backward',
    ];

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $isVerbose = $this->output->isVerbose();

        if ($isDryRun) {
            $this->warn('DRY RUN mode enabled: no database writes will be performed.');
        }

        $query = HikingRoute::query()
            ->where('app_id', 2)
            ->whereNotNull('properties');

        $total = $query->count();

        if ($total === 0) {
            $this->info('No records found. Nothing to do.');

            return 0;
        }

        $this->info("Found {$total} records to process.");

        $fixed = 0;
        $skipped = 0;
        $errors = 0;
        $noDemIds = [];

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(100, function ($routes) use (
            $isDryRun,
            $isVerbose,
            $bar,
            &$fixed,
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

                    // 1. rimuovi manual_data se presente
                    $hadManualData = array_key_exists('manual_data', $properties);
                    unset($properties['manual_data']);

                    // 2. leggi sorgenti
                    $demData = $this->safeArray($properties['dem_data'] ?? null);
                    $osmData = $this->safeArray($properties['osm_data'] ?? null);
                    $osmid = $route->osmid ?? null;

                    // 3. ripristina ogni campo top-level con priorità OSM → DEM → null
                    $hasNoSource = false;
                    foreach (self::DEM_FIELDS as $field) {
                        $osmValue = ($osmid !== null) ? ($osmData[$field] ?? null) : null;
                        $demValue = $demData[$field] ?? null;

                        if ($osmValue !== null) {
                            $properties[$field] = $osmValue;
                        } elseif ($demValue !== null) {
                            $properties[$field] = $demValue;
                        } else {
                            $properties[$field] = null;
                            $hasNoSource = true;
                        }
                    }

                    if ($hasNoSource) {
                        $noDemIds[] = $route->id;
                    }

                    Log::info('osm2cai:cleanup-si-hiking-routes-manual-data', [
                        'id' => $route->id,
                        'had_manual_data' => $hadManualData,
                        'dry_run' => $isDryRun,
                    ]);

                    if (! $isDryRun) {
                        $route->properties = $properties;
                        $route->saveQuietly();
                    }

                    $fixed++;
                    if ($isVerbose) {
                        $mode = $isDryRun ? 'DRY-RUN' : 'FIXED';
                        $note = $hadManualData ? '(manual_data removed)' : '(already clean)';
                        $this->newLine();
                        $this->line("  {$mode} [{$route->id}] {$note}");
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

        $this->info("Fixed: {$fixed}");
        $this->info("Skipped: {$skipped}");

        if ($errors > 0) {
            $this->warn("Errors: {$errors}");
        }

        if (! empty($noDemIds)) {
            $this->newLine();
            $this->warn('The following records were ' . ($isDryRun ? 'identified' : 'fixed') . ' but have no DEM, OSM or manual source for at least one field (top-level set to null):');
            $this->warn('IDs: ' . implode(', ', $noDemIds));
        }

        return 0;
    }

    private function safeArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
