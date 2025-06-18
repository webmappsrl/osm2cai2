<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use Illuminate\Console\Command;

final class PopulateHikingRoutesPropertiesCommand extends Command
{
    protected $signature = 'osm2cai:populate-hiking-routes-properties 
                           {--chunk=100 : Number of records to process at once}';

    protected $description = 'Populate the properties column of hiking routes with data extracted from osmfeatures_data for Elasticsearch';

    public function handle(): int
    {
        $this->info('Starting to populate hiking routes properties...');

        $hikingRoutes = HikingRoute::whereNotNull(['osmfeatures_data', 'properties'])->get();
        $totalRoutes = $hikingRoutes->count();

        $this->info("Found {$totalRoutes} hiking routes with osmfeatures_data");

        if ($totalRoutes === 0) {
            $this->warn('No hiking routes found with osmfeatures_data');
            return Command::SUCCESS;
        }

        $processedCount = 0;
        $updatedCount = 0;
        $errorCount = 0;

        $progressBar = $this->output->createProgressBar($totalRoutes);
        $progressBar->start();

        foreach ($hikingRoutes as $hikingRoute) {
            try {
                $properties = HikingRoute::extractPropertiesFromOsmfeatures($hikingRoute->osmfeatures_data);
                $hikingRoute->updateQuietly(['properties' => $properties]);
                ++$updatedCount;
                ++$processedCount;
            } catch (\Throwable $e) {
                ++$errorCount;
                $this->error("\nError processing HikingRoute ID {$hikingRoute->id}: {$e->getMessage()}");
            }
            $progressBar->advance();
        }

        $progressBar->finish();

        $this->newLine(2);
        $this->info('Process completed!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Processed', $processedCount],
                ['Successfully Updated', $updatedCount],
                ['Errors', $errorCount],
            ]
        );

        return Command::SUCCESS;
    }
}
