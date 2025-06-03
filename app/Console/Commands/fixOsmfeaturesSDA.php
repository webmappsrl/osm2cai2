<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class fixOsmfeaturesSDA extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:fix-osmfeatures-sda {--dry-run : Whether to simulate the command without actual updates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix osmfeatures_data.properties.osm2cai_status if it is not 4 when HikingRoute osm2cai_status is 4';

    private bool $isDryRun;

    private int $processedCount = 0;

    private int $updatedCount = 0;

    private array $updatedRouteIds = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->isDryRun = $this->option('dry-run');
        $this->displayStartMessage();

        $hikingRoutes = HikingRoute::where('osm2cai_status', 4)->get();

        foreach ($hikingRoutes as $route) {
            $this->processRoute($route);
        }

        $this->displaySummary();

        return Command::SUCCESS;
    }

    private function displayStartMessage(): void
    {
        $prefix = $this->isDryRun ? '[DRY RUN] ' : '';
        $this->info($prefix.'Starting fix of osmfeatures_data.properties.osm2cai_status...');
    }

    private function processRoute(HikingRoute $route): void
    {
        $this->processedCount++;

        $osmfeaturesData = $this->getOsmfeaturesData($route);
        $updateResult = $this->updateOsmfeaturesStatus($osmfeaturesData, $route);

        if ($updateResult['needsUpdate']) {
            $this->handleUpdate($route, $osmfeaturesData, $updateResult['logMessage']);
        }
    }

    private function getOsmfeaturesData(HikingRoute $route): array
    {
        $data = $route->osmfeatures_data;

        if (! is_array($data)) {
            $data = $this->tryDecodeFromString($route) ?? [];
        }

        return $this->ensurePropertiesStructure($data);
    }

    private function tryDecodeFromString(HikingRoute $route): ?array
    {
        $rawData = $route->getRawOriginal('osmfeatures_data');

        if (! is_string($rawData)) {
            return null;
        }

        $decodedData = json_decode($rawData, true);

        return (json_last_error() === JSON_ERROR_NONE && is_array($decodedData))
            ? $decodedData
            : null;
    }

    private function ensurePropertiesStructure(array $data): array
    {
        if (! isset($data['properties'])) {
            $data['properties'] = [];
        }

        return $data;
    }

    private function updateOsmfeaturesStatus(array $osmfeaturesData, HikingRoute $route): array
    {
        $currentStatus = $osmfeaturesData['properties']['osm2cai_status'] ?? null;

        if ($currentStatus == 4) {
            return ['needsUpdate' => false, 'logMessage' => ''];
        }

        $osmfeaturesData['properties']['osm2cai_status'] = 4;
        $statusLabel = $currentStatus ?? 'missing';
        $action = $this->isDryRun ? 'would be updated' : 'updated';

        $logMessage = "Route ID {$route->id}: osmfeatures_data.properties.osm2cai_status was '{$statusLabel}', {$action} to 4.";

        return [
            'needsUpdate' => true,
            'logMessage' => $logMessage,
            'updatedData' => $osmfeaturesData,
        ];
    }

    private function handleUpdate(HikingRoute $route, array $osmfeaturesData, string $logMessage): void
    {
        $this->updatedCount++;
        $this->updatedRouteIds[] = $route->id;

        $this->logUpdate($logMessage);

        if (! $this->isDryRun) {
            $this->saveRoute($route, $osmfeaturesData);
        }
    }

    private function logUpdate(string $message): void
    {
        $prefix = $this->isDryRun ? '[DRY RUN] ' : '';
        $this->info($prefix.$message);
    }

    private function saveRoute(HikingRoute $route, array $osmfeaturesData): void
    {
        $route->osmfeatures_data = $osmfeaturesData;
        $route->updateQuietly(['osmfeatures_data']);
    }

    private function displaySummary(): void
    {
        if ($this->isDryRun) {
            $this->displayDryRunSummary();
        } else {
            $this->displayExecutionSummary();
        }
    }

    private function displayDryRunSummary(): void
    {
        $this->info("[DRY RUN] Simulation complete. Processed {$this->processedCount} routes.");
        $this->info("[DRY RUN] {$this->updatedCount} routes would be updated.");

        if ($this->updatedCount > 0) {
            $ids = implode(', ', $this->updatedRouteIds);
            $this->info("[DRY RUN] IDs that would be updated: {$ids}");
        }
    }

    private function displayExecutionSummary(): void
    {
        $message = "fixOsmfeaturesSDA command finished. Processed {$this->processedCount} routes. {$this->updatedCount} routes were updated.";

        $this->info($message);
        Log::info("fixOsmfeaturesSDA command finished. {$this->updatedCount} routes were updated out of {$this->processedCount} processed with osm2cai_status = 4.");
    }
}
