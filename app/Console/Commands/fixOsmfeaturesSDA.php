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

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('[DRY RUN] Starting simulation of osmfeatures_data.properties.osm2cai_status fix...');
        } else {
            $this->info('Starting fix of osmfeatures_data.properties.osm2cai_status...');
        }

        $hikingRoutes = HikingRoute::where('osm2cai_status', 4)->get();
        $processedCount = 0;
        $wouldUpdateCount = 0;
        $actuallyUpdatedCount = 0;
        $routesToUpdateIds = [];

        foreach ($hikingRoutes as $route) {
            $processedCount++;
            $osmFeaturesData = $route->osmfeatures_data; // This will be an array due to model casting
            $originalOsmFeaturesDataForLog = json_encode($route->getOriginal('osmfeatures_data')); // Get original raw data for logging if needed

            $needsUpdateThisRoute = false;
            $logMessages = []; // Collect log messages for this route

            if (is_array($osmFeaturesData) && isset($osmFeaturesData['properties']['osm2cai_status'])) {
                if ($osmFeaturesData['properties']['osm2cai_status'] != 4) {
                    $oldStatus = $osmFeaturesData['properties']['osm2cai_status'];
                    $osmFeaturesData['properties']['osm2cai_status'] = 4;
                    $needsUpdateThisRoute = true;
                    $logMessages[] = "Route ID {$route->id}: osmfeatures_data.properties.osm2cai_status was '{$oldStatus}', " . ($isDryRun ? "would be changed" : "changed") . " to 4.";
                }
            } elseif (is_string($route->getRawOriginal('osmfeatures_data'))) { // Check raw original if cast resulted in non-array initially
                // This case might indicate a malformed JSON string in the DB not caught by casting or an edge case.
                // The model's cast to array should handle valid JSON strings.
                // If $osmFeaturesData is NOT an array here, it means the string was invalid JSON or null.
                // The 'else' block below will handle initialization for null or unexpected structures.
                // Let's assume the string was meant to be JSON and try to decode for logging/dry-run purposes.
                $decodedData = json_decode($route->getRawOriginal('osmfeatures_data'), true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedData) && isset($decodedData['properties']['osm2cai_status'])) {
                    if ($decodedData['properties']['osm2cai_status'] != 4) {
                        $oldStatus = $decodedData['properties']['osm2cai_status'];
                        // For dry run, we show what would happen. For actual run, $osmFeaturesData will be modified by the 'else' block if necessary
                        $osmFeaturesData = $decodedData; // Simulate for modification check
                        $osmFeaturesData['properties']['osm2cai_status'] = 4;
                        $needsUpdateThisRoute = true;
                        $logMessages[] = "Route ID {$route->id}: osmfeatures_data (from JSON string) .properties.osm2cai_status was '{$oldStatus}', " . ($isDryRun ? "would be changed" : "changed") . " to 4.";
                    } else {
                        // Status is already 4 in the decoded JSON string
                        $logMessages[] = "Route ID {$route->id}: osmfeatures_data (from JSON string) .properties.osm2cai_status is already 4. No change needed based on string content.";
                    }
                } else {
                    // String is not valid JSON or lacks expected structure, fall through to 'else' for initialization logic
                    $logMessages[] = "Route ID {$route->id}: osmfeatures_data was a string but could not be decoded or structure is not as expected ('{$route->getRawOriginal('osmfeatures_data')}'). Will attempt to initialize/fix.";
                    // Let the 'else' block handle initialization
                    if (!is_array($osmFeaturesData)) $osmFeaturesData = []; // Ensure $osmFeaturesData is an array for the next step
                    if (!isset($osmFeaturesData['properties'])) $osmFeaturesData['properties'] = [];
                    $oldStatus = $osmFeaturesData['properties']['osm2cai_status'] ?? 'missing/malformed';
                    if (($osmFeaturesData['properties']['osm2cai_status'] ?? null) != 4) {
                        $osmFeaturesData['properties']['osm2cai_status'] = 4;
                        $needsUpdateThisRoute = true;
                        $logMessages[] = "Route ID {$route->id}: Based on previous string state, osmfeatures_data.properties.osm2cai_status was '{$oldStatus}', " . ($isDryRun ? "would be set" : "set") . " to 4.";
                    }
                }
            } else { // Handles null, or already-array $osmFeaturesData missing keys
                $isInitialization = false;
                if (!is_array($osmFeaturesData)) { // If it was null or something else
                    $osmFeaturesData = [];
                    $isInitialization = true;
                }
                if (!isset($osmFeaturesData['properties'])) {
                    $osmFeaturesData['properties'] = [];
                    $isInitialization = true;
                }

                $oldStatus = $osmFeaturesData['properties']['osm2cai_status'] ?? 'missing';
                if (($osmFeaturesData['properties']['osm2cai_status'] ?? null) != 4) {
                    $osmFeaturesData['properties']['osm2cai_status'] = 4;
                    $needsUpdateThisRoute = true;
                    $action = $isInitialization ? "initialized" : "set";
                    $logMessages[] = "Route ID {$route->id}: osmfeatures_data.properties.osm2cai_status was '{$oldStatus}', " . ($isDryRun ? "would be {$action}" : "{$action}") . " to 4. Original raw: {$originalOsmFeaturesDataForLog}";
                }
            }

            if ($needsUpdateThisRoute) {
                $wouldUpdateCount++;
                $routesToUpdateIds[] = $route->id;
                foreach ($logMessages as $msg) {
                    if ($isDryRun) $this->line("[DRY RUN] " . $msg);
                    else $this->info($msg);
                }
                if (!$isDryRun) {
                    $route->osmfeatures_data = $osmFeaturesData; // Assign the modified array
                    $route->updateQuietly(['osmfeatures_data']);
                    $actuallyUpdatedCount++;
                }
            } else {
                // Optional: Log routes that were checked but didn't need updates
                // if ($isDryRun) $this->line("[DRY RUN] Route ID {$route->id}: No update needed.");
            }
        }

        if ($isDryRun) {
            $this->info("[DRY RUN] Simulation complete. Processed {$processedCount} routes.");
            $this->info("[DRY RUN] {$wouldUpdateCount} routes would be updated.");
            if ($wouldUpdateCount > 0) {
                $this->info("[DRY RUN] IDs that would be updated: " . implode(', ', $routesToUpdateIds));
            }
        } else {
            $this->info("fixOsmfeaturesSDA command finished. Processed {$processedCount} routes. {$actuallyUpdatedCount} routes were updated.");
            Log::info("fixOsmfeaturesSDA command finished. {$actuallyUpdatedCount} routes were updated out of {$processedCount} processed with osm2cai_status = 4.");
        }
        return Command::SUCCESS;
    }
}
