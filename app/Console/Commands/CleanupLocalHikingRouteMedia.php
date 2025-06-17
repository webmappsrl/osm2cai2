<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CleanupLocalHikingRouteMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:cleanup-local-hr-media';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes local media folders for Hiking Routes that have been successfully migrated to the wmfe disk.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting cleanup of local Hiking Route media...');

        // Find media items that have been migrated to the 'wmfe' disk.
        $migratedMedia = Media::where('model_type', HikingRoute::class)
            ->where('disk', 'wmfe')
            ->get();

        if ($migratedMedia->isEmpty()) {
            $this->info('No migrated media found. Nothing to clean up.');

            return 0;
        }

        $progressBar = $this->output->createProgressBar($migratedMedia->count());
        $this->info("Found {$migratedMedia->count()} migrated media items to clean up.");

        $deletedCount = 0;

        foreach ($migratedMedia as $media) {
            // The old local path was structured as 'public/{media_id}'.
            // We get the full path to the directory.
            $localDirectoryPath = storage_path('app/public/'.$media->id);

            if (File::isDirectory($localDirectoryPath)) {
                if (File::deleteDirectory($localDirectoryPath)) {
                    $deletedCount++;
                } else {
                    $this->warn("\n[FAILED TO DELETE] Could not delete directory: {$localDirectoryPath}");
                }
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info("\nCleanup completed. Successfully deleted {$deletedCount} local media directories.");

        return 0;
    }
}
