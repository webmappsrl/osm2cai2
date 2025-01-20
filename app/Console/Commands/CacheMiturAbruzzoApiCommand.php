<?php

namespace App\Console\Commands;

use App\Jobs\CacheMiturAbruzzoDataJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class CacheMiturAbruzzoApiCommand extends Command
{
    protected $signature = 'osm2cai:cache-mitur-abruzzo-api 
        {model=Region : The model name} 
        {id? : The model id}
        {--all : Cache for all available models}';

    protected $description = 'Store MITUR Abruzzo API data using AWS S3. Only HikingRoutes with osm2cai_status 4 are cached.';

    protected $models = ['Region', 'CaiHut', 'Club', 'EcPoi', 'HikingRoute', 'MountainGroups'];

    public function handle()
    {
        if (! App::environment('production')) {
            if (! $this->confirm('This command is meant to be run in production. By continuing, you will update cached file on AWS S3 with your local data. Do you wish to continue?')) {
                $this->info('Command cancelled.');

                return;
            }
        }

        if ($this->option('all')) {
            foreach ($this->models as $model) {
                $this->processModel($model);
            }

            return;
        }

        $this->processModel($this->argument('model'));
    }

    protected function processModel($modelName)
    {
        try {
            $modelClass = App::make("App\\Models\\{$modelName}");
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return;
        }
        $className = class_basename($modelClass);

        $query = $className === 'HikingRoute'
            ? $modelClass::where('osm2cai_status', 4)
            : $modelClass::query();

        if ($id = $this->argument('id')) {
            $query->where('id', $id);
        }

        $count = $query->count();

        if ($count === 0 && $className === 'HikingRoute') {
            $this->error('No hiking routes found with osm2cai_status 4');
            Log::error('No hiking routes found with osm2cai_status 4');

            return;
        }

        $this->info("Processing {$count} {$className}");
        Log::info("Starting cache process for {$count} {$className}");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($query->cursor() as $model) {
            try {
                CacheMiturAbruzzoDataJob::dispatch($className, $model->id);
            } catch (\Exception $e) {
                Log::error("Failed to dispatch job for {$className} {$model->id}: ".$e->getMessage());
                $this->error("\nFailed to dispatch job for {$className} {$model->id}: ".$e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->info("\nAll jobs dispatched successfully");
        Log::info("Finished dispatching cache jobs for {$className}");
    }
}
