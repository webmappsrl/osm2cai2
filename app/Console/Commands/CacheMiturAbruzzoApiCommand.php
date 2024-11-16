<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use App\Jobs\CacheMiturAbruzzoData;

class CacheMiturAbruzzoApiCommand extends Command
{
    protected $signature = 'osm2cai2:cache-mitur-abruzzo-api 
        {model=Region : The model name} 
        {id? : The model id}
        {--queue : Process through queue}';

    protected $description = 'Store MITUR Abruzzo API data using AWS S3';

    public function handle()
    {
        $modelClass = App::make("App\\Models\\{$this->argument('model')}");
        $className = class_basename($modelClass);

        $query = $className === 'HikingRoute'
            ? $modelClass::where('osm2cai_status', 4)
            : $modelClass::query();

        if ($id = $this->argument('id')) {
            $query->where('id', $id);
        }

        $count = $query->count();

        if ($count === 0 && $className === 'HikingRoute') {
            $this->error("No hiking routes found with osm2cai_status 4");
            Log::error("No hiking routes found with osm2cai_status 4");
            return;
        }

        $this->info("Processing {$count} {$className}");
        Log::info("Starting cache process for {$count} {$className}");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($query->cursor() as $model) {
            try {
                CacheMiturAbruzzoData::dispatch($className, $model->id);
            } catch (\Exception $e) {
                Log::error("Failed to dispatch job for {$className} {$model->id}: " . $e->getMessage());
                $this->error("\nFailed to dispatch job for {$className} {$model->id}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->info("\nAll jobs dispatched successfully");
        Log::info("Finished dispatching cache jobs for {$className}");
    }
}
