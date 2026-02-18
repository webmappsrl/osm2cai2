<?php

namespace App\Console\Commands;

use App\Jobs\CalculateIntersectionsJob;
use App\Models\HikingRoute;
use Illuminate\Console\Command;
use Throwable;

class FixMissingHikingRoutesSectorsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:fix-missing-hiking-routes-sectors
                            {--id=* : Process only specific hiking route IDs}
                            {--model=sectors : Target model (regions, provinces, areas, sectors)}
                            {--sync : Execute intersection computation synchronously}
                            {--queue=geometric-computations : Queue name for async mode}
                            {--chunk=200 : Chunk size used to scan routes}
                            {--dry-run : Show how many routes would be processed without dispatching jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate intersections for hiking routes that are missing assignments for regions, provinces, areas, or sectors.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $modelType = strtolower((string) $this->option('model'));
        $modelConfig = $this->resolveModelConfig($modelType);

        if ($modelConfig === null) {
            $this->error("Invalid --model value '{$modelType}'. Allowed values: regions, provinces, areas, sectors.");

            return Command::FAILURE;
        }

        $relation = $modelConfig['relation'];
        $intersectingModelClass = $modelConfig['class'];
        $labelPlural = $modelConfig['label_plural'];
        $pivotTable = $modelConfig['pivot_table'];

        $idsOption = collect($this->option('id'))
            ->filter(fn($id) => $id !== null && $id !== '')
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $chunkSize = max((int) $this->option('chunk'), 1);
        $syncMode = (bool) $this->option('sync');
        $dryRun = (bool) $this->option('dry-run');
        $queue = (string) $this->option('queue');

        $globalMissingBefore = HikingRoute::query()
            ->whereNotNull('geometry')
            ->where(function ($query) {
                $query->whereNull('osm2cai_status')
                    ->orWhere('osm2cai_status', '!=', 4);
            })
            ->whereDoesntHave($relation)
            ->count();

        $baseQuery = HikingRoute::query()
            ->whereNotNull('geometry')
            ->where(function ($query) {
                $query->whereNull('osm2cai_status')
                    ->orWhere('osm2cai_status', '!=', 4);
            })
            ->whereRaw("NOT EXISTS (SELECT 1 FROM {$pivotTable} WHERE {$pivotTable}.hiking_route_id = hiking_routes.id)");


        if (! empty($idsOption)) {
            $baseQuery->whereIn('id', $idsOption);
        }

        $total = (clone $baseQuery)->count();

        $this->info("Before assignment - total hiking routes without {$labelPlural}: {$globalMissingBefore}");
        $this->info("Before assignment - hiking routes without {$labelPlural} in current scope: {$total}");

        if ($total === 0) {
            $this->info("No hiking routes with missing {$labelPlural} found for the selected scope.");

            return Command::SUCCESS;
        }

        $this->line('Mode: ' . ($syncMode ? 'sync' : "async (queue: {$queue})"));
        $this->line("Chunk size: {$chunkSize}");

        if ($dryRun) {
            $previewIds = (clone $baseQuery)->orderBy('id')->limit(20)->pluck('id')->all();
            $this->line('Dry-run enabled, no jobs dispatched.');
            $this->line('Preview IDs (max 20): ' . implode(', ', $previewIds));

            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $processed = 0;
        $failed = [];

        (clone $baseQuery)
            ->orderBy('id')
            ->chunkById($chunkSize, function ($routes) use ($syncMode, $queue, $intersectingModelClass, &$processed, &$failed, $bar) {
                foreach ($routes as $route) {
                    try {
                        if ($syncMode) {
                            CalculateIntersectionsJob::dispatchSync($route, $intersectingModelClass);
                        } else {
                            CalculateIntersectionsJob::dispatch($route, $intersectingModelClass)->onQueue($queue);
                        }
                    } catch (Throwable $e) {
                        $failed[] = $route->id;
                        $this->error("\nFailed processing hiking route {$route->id}: {$e->getMessage()}");
                    } finally {
                        $processed++;
                        $bar->advance();
                    }
                }
            }, 'id');

        $bar->finish();
        $this->newLine();

        $this->info("Processed {$processed} hiking routes.");

        if (! empty($failed)) {
            $this->warn('Failed IDs: ' . implode(', ', $failed));
        }

        if ($syncMode) {
            $remaining = (clone $baseQuery)->count();
            $this->info("Remaining hiking routes without {$labelPlural}: {$remaining}");
        } else {
            $this->line('Jobs dispatched. Wait for queue workers/Horizon to complete and rerun with --dry-run to verify remaining routes.');
        }

        return empty($failed) ? Command::SUCCESS : Command::FAILURE;
    }

    private function resolveModelConfig(string $modelType): ?array
    {
        return match ($modelType) {
            'regions' => [
                'relation' => 'regions',
                'class' => 'App\\Models\\Region',
                'label_plural' => 'regions',
                'pivot_table' => 'hiking_route_region',
            ],
            'provinces' => [
                'relation' => 'provinces',
                'class' => 'App\\Models\\Province',
                'label_plural' => 'provinces',
                'pivot_table' => 'hiking_route_province',
            ],
            'areas' => [
                'relation' => 'areas',
                'class' => 'App\\Models\\Area',
                'label_plural' => 'areas',
                'pivot_table' => 'area_hiking_route',
            ],
            'sectors' => [
                'relation' => 'sectors',
                'class' => 'App\\Models\\Sector',
                'label_plural' => 'sectors',
                'pivot_table' => 'hiking_route_sector',
            ],
            default => null,
        };
    }
}
