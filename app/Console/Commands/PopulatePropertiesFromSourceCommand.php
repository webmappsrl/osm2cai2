<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class PopulatePropertiesFromSourceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:populate-properties
                           {--model=HikingRoute : The model class to process, e.g., App\\Models\\HikingRoute}
                           {--source=osmfeatures_data : The source JSON column name}
                           {--destination=properties : The destination JSON column name}
                           {--method=extractPropertiesFromOsmfeatures : The static method on the model to extract data, e.g., extractPropertiesFromOsmfeatures}
                           {--chunk=100 : Number of records to process at once}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate a destination JSON column by processing a source column using a model-specific static method.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $modelClass = 'App\\Models\\' . $this->option('model');
        $sourceColumn = $this->option('source');
        $destinationColumn = $this->option('destination');
        $methodName = $this->option('method');
        $chunkSize = (int) $this->option('chunk');

        if (! $this->validateInputs($modelClass, $methodName)) {
            return Command::FAILURE;
        }

        $this->info("Starting to populate {$destinationColumn} for {$modelClass} from {$sourceColumn}...");

        $query = $modelClass::whereNotNull($sourceColumn);
        $totalRecords = $query->count();

        if ($totalRecords === 0) {
            $this->warn("No records found for {$modelClass} with a non-null {$sourceColumn}.");

            return Command::SUCCESS;
        }

        $this->info("Found {$totalRecords} records to process.");

        $processedCount = 0;
        $updatedCount = 0;
        $errorCount = 0;

        $progressBar = $this->output->createProgressBar($totalRecords);
        $progressBar->start();

        $query->chunkById($chunkSize, function (Collection $records) use (&$processedCount, &$updatedCount, &$errorCount, $progressBar, $sourceColumn, $destinationColumn, $methodName, $modelClass) {
            foreach ($records as $record) {
                try {
                    $existingProperties = $record->{$destinationColumn} ?? [];
                    $sourceData = $record->{$sourceColumn};

                    // Pass record id as second argument to the static method
                    $newProperties = $modelClass::$methodName($sourceData, $record->id);

                    $mergedProperties = array_merge($existingProperties, $newProperties);
                    $record->updateQuietly([$destinationColumn => $mergedProperties]);
                    ++$updatedCount;
                } catch (\Throwable $e) {
                    ++$errorCount;
                    $this->error("\nError processing {$modelClass} ID {$record->id}: {$e->getMessage()}");
                }
                ++$processedCount;
                $progressBar->advance();
            }
        });

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

    private function validateInputs(string $modelClass, string $methodName): bool
    {
        if (! class_exists($modelClass)) {
            $this->error("Model class '{$modelClass}' does not exist.");

            return false;
        }

        if (! is_subclass_of($modelClass, Model::class)) {
            $this->error("Class '{$modelClass}' is not an Eloquent Model.");

            return false;
        }

        if (! method_exists($modelClass, $methodName)) {
            $this->error("Static method '{$methodName}' does not exist on model '{$modelClass}'.");

            return false;
        }

        return true;
    }
}
