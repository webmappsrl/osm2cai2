<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Wm\WmPackage\Models\Media;
use App\Models\UgcPoi;
use App\Models\UgcTrack;

class DownloadMediaFromGeohubCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'osm2cai:download-media-from-geohub 
                           {--limit=100 : Maximum number of images to download per run}
                           {--force : Re-download even if file already exists physically}
                           {--skip-existing : Skip download if file already exists (default behavior)}';

    /**
     * The console command description.
     */
    protected $description = 'Download images from Geohub to local storage (AWS/Minio)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('📥 Starting image download from Geohub...');
        $this->newLine();

        $limit = (int) $this->option('limit');
        $force = $this->option('force');

        // Get media records with Geohub URLs, ordered by most recent UGC first
        $query = DB::table('media')
            ->where('collection_name', 'default')
            ->whereIn('model_type', ['App\\Models\\UgcPoi', 'App\\Models\\UgcTrack'])
            ->whereRaw("custom_properties->>'relative_url' LIKE 'https://geohub.webmapp.it%'");

        // If not forcing, exclude already downloaded images (size > 0)
        // Note: We still check physical file existence in downloadImageFromGeohub()
        if (!$force) {
            $query->where('size', '<=', 0);
        }

        // Order by most recent media first (better for testing new uploads)
        $mediaRecords = $query
            ->orderBy('created_at', 'desc');
            
        // Apply limit only if greater than 0 (0 means no limit)
        if ($limit > 0) {
            $mediaRecords = $mediaRecords->limit($limit);
        }
        
        $mediaRecords = $mediaRecords->get();

        if ($mediaRecords->isEmpty()) {
            $this->info('ℹ️ No Geohub images found to download');
            return Command::SUCCESS;
        }

        $this->info("📊 Found {$mediaRecords->count()} images to download");
        $progressBar = $this->output->createProgressBar($mediaRecords->count());
        $progressBar->start();

        $downloaded = 0;
        $failed = 0;

        foreach ($mediaRecords as $mediaRecord) {
            $customProperties = json_decode($mediaRecord->custom_properties, true);
            $geohubUrl = $customProperties['relative_url'] ?? null;

            if ($geohubUrl && $this->downloadImageFromGeohub($mediaRecord, $geohubUrl)) {
                $downloaded++;
            } else {
                $failed++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("✅ Download completed: {$downloaded} successful, {$failed} failed");

        return Command::SUCCESS;
    }

    /**
     * Download a single image from Geohub directly to storage (respecting filesystem config)
     */
    private function downloadImageFromGeohub($mediaRecord, string $geohubUrl): bool
    {
        try {
            // Get the existing media record  
            $existingMedia = Media::find($mediaRecord->id);
            
            if (!$existingMedia) {
                Log::warning("Media record {$mediaRecord->id} not found");
                return false;
            }

            // Check if file already exists and has size (unless force is used)
            if (!$this->option('force') && $existingMedia->size > 0) {
                return true; // Already downloaded
            }

            // Download the image
            $response = Http::timeout(30)->get($geohubUrl);
            
            if (!$response->successful()) {
                return false;
            }

            $imageContent = $response->body();
            
            // Use the PathGeneratorFactory to get the correct path generator for this media
            $disk = Storage::disk($existingMedia->disk);
            
            // Let MediaLibrary determine the correct path generator via factory
            $pathGenerator = \Spatie\MediaLibrary\Support\PathGenerator\PathGeneratorFactory::create($existingMedia);
            $filePath = $pathGenerator->getPath($existingMedia) . '/' . $existingMedia->file_name;
            
            // Save to the configured storage (respects AWS/Minio config and correct path structure)
            $disk->put($filePath, $imageContent);
            
            // Update the media record
            $existingMedia->update([
                'size' => strlen($imageContent),
                'updated_at' => now(),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::warning("Failed to download image from {$geohubUrl}: " . $e->getMessage());
            return false;
        }
    }
}
