<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Conversions\ConversionCollection;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Wm\WmPackage\Services\StorageService;

class UpdateHikingRouteMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:update-hiking-route-media';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update existing media for Hiking Routes with app_id, user_id, and geometry, and ensures they are on the correct disk.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting update of Hiking Route media...');

        $systemUser = User::where('email', 'team@webmapp.it')->first() ?? User::find(1);
        if (! $systemUser) {
            $this->error('System user not found. Please ensure a user with email admin@webmapp.it or ID 1 exists.');

            return 1;
        }
        $this->info("Using System User ID: {$systemUser->id}");

        $storageService = new StorageService;
        $targetDisk = $storageService->getMediaDisk();

        $mediaItems = Media::where('model_type', HikingRoute::class)
            ->where('collection_name', 'feature_image')
            ->get();

        if ($mediaItems->isEmpty()) {
            $this->info('No Hiking Route feature images to process.');

            return 0;
        }

        $progressBar = $this->output->createProgressBar($mediaItems->count());
        $this->info("Found {$mediaItems->count()} feature images to process.");

        foreach ($mediaItems as $media) {
            if ($media->disk !== 'wmfe') {
                $sourceDisk = Storage::disk($media->disk);
                $sourcePath = $media->id.'/'.$media->file_name;

                if ($sourceDisk->exists($sourcePath)) {
                    $mediaContent = $sourceDisk->get($sourcePath);

                    $media->disk = 'wmfe';
                    $destinationPath = $media->getPath();

                    $targetDisk->put($destinationPath, $mediaContent);

                    $media->conversions_disk = 'wmfe';

                    // Manually dispatch the conversion job for an older version of Spatie Media Library
                    $conversions = ConversionCollection::createForMedia($media);
                    dispatch(new PerformConversionsJob($conversions, $media));

                    $this->line("\nQueued thumbnail regeneration for Media ID {$media->id}.");
                } else {
                    $this->warn("\n[SKIPPING FILE MOVE] Media ID {$media->id}: file not found at '{$sourcePath}' on disk '{$media->disk}'.");
                }
            }

            $hikingRoute = $media->model;
            if (! $hikingRoute) {
                $this->warn("\nSkipping Media ID: {$media->id} - Associated Hiking Route not found.");
                $progressBar->advance();

                continue;
            }

            if (! $hikingRoute->geometry) {
                $this->warn("\nSkipping geometry update for Media ID: {$media->id} - Hiking Route geometry not found.");
            } else {
                $centroid = DB::selectOne('SELECT ST_AsText(ST_Force3D(ST_Centroid(?))) as point', [$hikingRoute->geometry]);
                $media->geometry = $centroid->point;
            }

            $media->app_id = 1;
            $media->user_id = $systemUser->id;
            $media->saveQuietly();

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info("\nUpdate of Hiking Route media completed!");

        return 0;
    }
}
