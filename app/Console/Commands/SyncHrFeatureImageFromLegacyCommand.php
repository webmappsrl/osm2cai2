<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncHrFeatureImageFromLegacyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:sync-hr-feature-image-from-legacy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command sync the feature_image column in the HikingRoute table from legacy osm2cai DB';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('[START] Importing hiking routes feature images from legacy database...');

        $legacyConnection = DB::connection('legacyosm2cai');
        $legacyHrs = $legacyConnection->table('hiking_routes')
            ->whereNotNull('feature_image')
            ->get(['relation_id', 'feature_image']);

        foreach ($legacyHrs as $lhr) {
            $osmfeaturesId = 'R'.$lhr->relation_id;
            $currentHr = HikingRoute::where('osmfeatures_id', $osmfeaturesId)->first();

            if ($currentHr) {
                // Build the full URL to the legacy image
                $featureImage = str_replace('public', 'storage', $lhr->feature_image);
                $legacyImageUrl = 'https://osm2cai.cai.it/'.$featureImage;

                // Verifiy if the image is already associated
                if (! $currentHr->getFirstMedia('feature_image')) {
                    if ($this->downloadAndAssociateImage($currentHr, $legacyImageUrl)) {
                        $this->info("Image downloaded and associated successfully for the hiking route {$osmfeaturesId}");
                    } else {
                        $this->error("Error during the download of the image for the hiking route {$osmfeaturesId}");
                        Log::error("Error during the download of the image from {$legacyImageUrl}");
                    }
                } else {
                    $this->info("The image already exists for the hiking route {$osmfeaturesId}, skipping the download");
                }
            }
        }

        $this->info('Synchronization of the hiking route feature images completed');
        Log::info('Synchronization of the hiking route feature images completed');
    }

    private function downloadAndAssociateImage(HikingRoute $hr, string $imageUrl): bool
    {
        try {
            // Download the image
            $response = Http::get($imageUrl);

            if ($response->successful()) {
                // Create a temporary file
                $tempFile = tempnam(sys_get_temp_dir(), 'hiking_route_');
                file_put_contents($tempFile, $response->body());

                // Determine the file extension from the URL
                $extension = pathinfo($imageUrl, PATHINFO_EXTENSION);
                if (empty($extension)) {
                    $extension = 'jpg'; // Default extension
                }

                // Rename the temporary file with the correct extension
                $tempFileWithExtension = $tempFile.'.'.$extension;
                rename($tempFile, $tempFileWithExtension);

                // Associate the image to the feature_image collection
                $hr->addMedia($tempFileWithExtension)
                    ->toMediaCollection('feature_image');

                // Clean up the temporary file
                @unlink($tempFileWithExtension);

                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error("Error during the download of the image for hiking route {$hr->id}: ".$e->getMessage());

            return false;
        }
    }
}
