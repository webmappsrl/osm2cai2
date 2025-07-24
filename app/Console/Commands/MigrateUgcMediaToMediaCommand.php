<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateUgcMediaToMediaCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'osm2cai:migrate-ugc-media-to-media 
                           {--verify : Only verify the migration without executing}
                           {--force : Force migration even if media table already contains migrated data}';

    /**
     * The console command description.
     */
    protected $description = 'Migrate data from legacy ugc_media table to the new media system';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting UGC Media Migration to Media System');
        $this->newLine();

        // Step 1: Verification phase
        if ($this->option('verify')) {
            return $this->verifyMigration();
        }

        // Step 2: Pre-migration checks
        if (!$this->preMigrationChecks()) {
            return Command::FAILURE;
        }

        // Step 3: Execute migration
        if (!$this->executeMigration()) {
            return Command::FAILURE;
        }

        // Step 4: Post-migration verification
        $this->postMigrationVerification();

        $this->info('✅ Migration completed successfully!');
        $this->newLine();
        
        $this->info('📋 Next steps:');
        $this->line('1. Download images: php artisan osm2cai:download-media-from-geohub');
        $this->line('2. Test the application thoroughly');
        $this->line('3. When ready, deprecate ugc_media table manually if needed');
        
        return Command::SUCCESS;
    }

    /**
     * Perform pre-migration checks
     */
    private function preMigrationChecks(): bool
    {
        $this->info('🔍 Performing pre-migration checks...');

        // Check if ugc_media table exists
        if (!Schema::hasTable('ugc_media')) {
            $this->error('❌ ugc_media table does not exist');
            return false;
        }

        // Check if media table exists
        if (!Schema::hasTable('media')) {
            $this->error('❌ media table does not exist');
            return false;
        }

        // Check if migration has already been run
        $existingMigrated = DB::table('media')
            ->where('collection_name', 'default')
            ->whereIn('model_type', ['App\\Models\\UgcPoi', 'App\\Models\\UgcTrack'])
            ->count();

        if ($existingMigrated > 0 && !$this->option('force')) {
            $this->error("❌ Migration appears to have already been run ({$existingMigrated} records found)");
            $this->line('Use --force to run anyway');
            return false;
        }

        // Count records to migrate
        $ugcMediaCount = DB::table('ugc_media')->count();
        $this->info("📊 Found {$ugcMediaCount} records in ugc_media to migrate");

        if ($ugcMediaCount === 0) {
            $this->warn('⚠️ No records found in ugc_media table');
            return true;
        }

        return true;
    }

    /**
     * Execute the migration
     */
    private function executeMigration(): bool
    {
        $this->info('🔄 Running migration...');

        try {
            // 1. Ensure required columns exist
            $this->ensureRequiredColumns();
            
            // 2. Clear any existing migrated data if force is used
            if ($this->option('force')) {
                DB::table('media')
            ->where('collection_name', 'default')
            ->whereIn('model_type', ['App\\Models\\UgcPoi', 'App\\Models\\UgcTrack'])
            ->delete();
                $this->info('🧹 Cleared existing migrated data');
            }
            
            // 3. Migrate data
            $this->migrateUgcMediaToMedia();

            $this->info('✅ Migration executed successfully');
            return true;

        } catch (\Exception $e) {
            $this->error('❌ Migration failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Ensure required columns exist in media table
     */
    private function ensureRequiredColumns(): void
    {
        $columnsAdded = false;
        
        if (!Schema::hasColumn('media', 'app_id')) {
            Schema::table('media', function ($table) {
                $table->string('app_id')->nullable()->after('order_column');
            });
            $this->info('➕ Added app_id column to media table');
            $columnsAdded = true;
        }

        // Check if geometry column exists (managed by wmpackage)
        if (!Schema::hasColumn('media', 'geometry')) {
            $this->error('❌ media table does not have geometry column (wmpackage schema issue)');
            return;
        }
        
        if (!$columnsAdded) {
            $this->info('✅ Required columns already exist');
        }
    }

    /**
     * Migrate data from ugc_media to media table
     */
    private function migrateUgcMediaToMedia(): void
    {
        $this->info('📦 Migrating ugc_media data to media table...');
        
        // Get all ugc_media records
        $ugcMediaRecords = DB::table('ugc_media')->get();
        $progressBar = $this->output->createProgressBar($ugcMediaRecords->count());
        $migratedCount = 0;

        foreach ($ugcMediaRecords as $ugcMedia) {
            // Determine target model
            $modelType = null;
            $modelId = null;
            $targetAppId = null;

            if ($ugcMedia->ugc_poi_id) {
                $modelType = 'App\\Models\\UgcPoi';
                $modelId = $ugcMedia->ugc_poi_id;
                
                // Get app_id from UgcPoi model
                $ugcPoi = DB::table('ugc_pois')->where('id', $modelId)->first();
                $targetAppId = $ugcPoi ? $ugcPoi->app_id : null;
                
                // If app_id is not numeric, try to determine from form content
                if ($targetAppId && !is_numeric($targetAppId)) {
                    $targetAppId = $this->determineAppIdFromForm($ugcPoi, $targetAppId);
                }
                
            } elseif ($ugcMedia->ugc_track_id) {
                $modelType = 'App\\Models\\UgcTrack';
                $modelId = $ugcMedia->ugc_track_id;
                
                // Get app_id from UgcTrack model
                $ugcTrack = DB::table('ugc_tracks')->where('id', $modelId)->first();
                $targetAppId = $ugcTrack ? $ugcTrack->app_id : null;
                
                // If app_id is not numeric, try to determine from form content
                if ($targetAppId && !is_numeric($targetAppId)) {
                    $targetAppId = $this->determineAppIdFromForm($ugcTrack, $targetAppId);
                }
            }

            // Skip if no model association
            if (!$modelType || !$modelId) {
                $progressBar->advance();
                continue;
            }

            // Get geometry from ugc_media or from associated UGC model
            $geometry = $ugcMedia->geometry;
            
            // If ugc_media doesn't have geometry, get it from the associated UGC model
            if (!$geometry) {
                if ($modelType === 'App\\Models\\UgcPoi') {
                    $ugcPoi = DB::table('ugc_pois')->where('id', $modelId)->first();
                    $geometry = $ugcPoi ? $ugcPoi->geometry : null;
                } elseif ($modelType === 'App\\Models\\UgcTrack') {
                    $ugcTrack = DB::table('ugc_tracks')->where('id', $modelId)->first();
                    $geometry = $ugcTrack ? $ugcTrack->geometry : null;
                }
            }

            // Skip if still no geometry (media table requires NOT NULL geometry)
            if (!$geometry) {
                $progressBar->advance();
                continue;
            }

            // Convert geometry and check if it's valid
            $convertedGeometry = $this->convertGeometryTo3D($geometry);
            
            // Skip if geometry conversion failed (media table requires NOT NULL geometry)
            if (!$convertedGeometry) {
                $progressBar->advance();
                continue;
            }

            // Create custom properties
            $customProperties = [
                'geohub_id' => $ugcMedia->geohub_id,
                'description' => $ugcMedia->description,
                'raw_data' => $ugcMedia->raw_data ? json_decode($ugcMedia->raw_data, true) : null,
                'taxonomy_wheres' => $ugcMedia->taxonomy_wheres,
                'relative_url' => $ugcMedia->relative_url,
                'legacy_ugc_media_id' => $ugcMedia->id,
                'original_app_id' => $ugcMedia->app_id, // Manteniamo l'originale per riferimento
            ];

            // Insert into media table
            DB::table('media')->insert([
                'model_type' => $modelType,
                'model_id' => $modelId,
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'collection_name' => 'default',
                'name' => $ugcMedia->name ?: 'Unnamed Media',
                'file_name' => $this->generateFileName($ugcMedia->relative_url),
                'mime_type' => $this->guessMimeType($ugcMedia->relative_url),
                'disk' => config('wm-media-library.disk_name', 'wmfe'),
                'conversions_disk' => config('wm-media-library.disk_name', 'wmfe'),
                'size' => 0,
                'manipulations' => json_encode([]),
                'custom_properties' => json_encode($customProperties),
                'generated_conversions' => json_encode([]),
                'responsive_images' => json_encode([]),
                'order_column' => 1,
                'app_id' => $targetAppId, // Uso l'app_id del modello UGC
                'geometry' => $convertedGeometry,
                'created_at' => $ugcMedia->created_at,
                'updated_at' => $ugcMedia->updated_at,
            ]);

            $migratedCount++;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("📈 Migrated {$migratedCount} records from ugc_media to media");
    }

    /**
     * Generate filename from relative_url
     */
    private function generateFileName(?string $relativeUrl): string
    {
        if (!$relativeUrl) {
            return 'unknown_file.jpg';
        }

        $filename = basename($relativeUrl);
        return $filename ?: 'unknown_file.jpg';
    }

    /**
     * Guess MIME type from file extension
     */
    private function guessMimeType(?string $relativeUrl): string
    {
        if (!$relativeUrl) {
            return 'image/jpeg';
        }

        $extension = strtolower(pathinfo($relativeUrl, PATHINFO_EXTENSION));
        
        return match($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg'
        };
    }

    /**
     * Convert geometry from ugc_media to geography POINT for media table and ensure 3D
     */
    private function convertGeometryTo3D($geometry)
    {
        if (!$geometry) {
            return null;
        }

        // Convert any geometry type to POINT and ensure 3D
        // If it's a MultiLineString, take the first point
        // If it's already a Point, use it directly
        $result = DB::selectOne(
            "SELECT ST_Force3DZ(
                CASE 
                    WHEN ST_GeometryType(?::geometry) = 'ST_MultiLineString' THEN 
                        ST_StartPoint(ST_GeometryN(?::geometry, 1))
                    WHEN ST_GeometryType(?::geometry) = 'ST_LineString' THEN 
                        ST_StartPoint(?::geometry)
                    ELSE 
                        ?::geometry
                END
            )::geography as geography_3d",
            [$geometry, $geometry, $geometry, $geometry, $geometry]
        );

        return $result ? $result->geography_3d : null;
    }

    /**
     * Determine app_id based on form content
     */
    private function determineAppIdFromForm($ugcModel, $originalAppId): ?string
    {
        // Parse properties if it's JSON
        $properties = is_string($ugcModel->properties) 
            ? json_decode($ugcModel->properties, true) 
            : $ugcModel->properties;

        if (!$properties || !isset($properties['form'])) {
            return null; // No form data, can't determine
        }

        $form = $properties['form'];
        $geohubAppId = null;

        // 1. Check form ID first (most reliable)
        if (isset($form['id'])) {
            $formId = strtolower($form['id']);
            
            switch ($formId) {
                case 'water':
                case 'spring':
                case 'source':
                    $geohubAppId = '58'; // it.webmapp.acquasorgente
                    break;
                    
                case 'poi':
                    // For generic POI, check waypointtype
                    if (isset($form['waypointtype'])) {
                        $waypointtype = strtolower($form['waypointtype']);
                        
                        if (in_array($waypointtype, ['flora', 'fauna', 'habitat'])) {
                            $geohubAppId = '26'; // it.webmapp.osm2cai (naturalistic)
                        }
                        
                        if (in_array($waypointtype, ['archaeological_site', 'archaeological_area', 'geological_site', 'signs'])) {
                            $geohubAppId = '26'; // it.webmapp.osm2cai (cultural)
                        }
                    }
                    break;
                    
                case 'archaeological_site':
                case 'archaeological_area': 
                case 'geological_site':
                case 'signs':
                    $geohubAppId = '26'; // it.webmapp.osm2cai
                    break;
            }
        }

        // 2. Check description for source hints
        if (!$geohubAppId && isset($form['description'])) {
            $description = strtolower($form['description']);
            
            if (strpos($description, 'inaturalist.org') !== false) {
                $geohubAppId = '26'; // iNaturalist data usually goes to osm2cai
            }
        }

        // 3. Fallback: try to extract from geohub_ pattern
        if (!$geohubAppId && strpos($originalAppId, 'geohub_') === 0) {
            $geohubAppId = str_replace('geohub_', '', $originalAppId);
        }

        // Convert Geohub app ID to local app ID
        return $this->mapGeohubToLocalAppId($geohubAppId);
    }

    /**
     * Map Geohub app IDs to local app IDs
     */
    private function mapGeohubToLocalAppId(?string $geohubAppId): ?string
    {
        if (!$geohubAppId) {
            return null;
        }

        // Mapping Geohub ID → Local ID
        $mapping = [
            '20' => '3', // it.webmapp.sicai
            '26' => '1', // it.webmapp.osm2cai  
            '58' => '2', // it.webmapp.acquasorgente
        ];

        return $mapping[$geohubAppId] ?? null;
    }

    /**
     * Verify migration results
     */
    private function verifyMigration(): int
    {
        $this->info('🔍 Verifying migration status...');

        $ugcMediaCount = Schema::hasTable('ugc_media') ? DB::table('ugc_media')->count() : 0;
        $migratedCount = DB::table('media')
            ->where('collection_name', 'default')
            ->whereIn('model_type', ['App\\Models\\UgcPoi', 'App\\Models\\UgcTrack'])
            ->count();

        $this->table(['Table', 'Count'], [
            ['ugc_media (original)', $ugcMediaCount],
            ['media (migrated)', $migratedCount],
        ]);

        if ($ugcMediaCount === $migratedCount) {
            $this->info('✅ Migration verification passed: All records migrated');
        } else {
            $this->warn("⚠️ Migration verification: {$ugcMediaCount} original vs {$migratedCount} migrated");
        }

        return Command::SUCCESS;
    }

    /**
     * Post-migration verification
     */
    private function postMigrationVerification(): bool
    {
        $this->info('🔍 Performing post-migration verification...');

        $ugcMediaCount = DB::table('ugc_media')->count();
        $migratedCount = DB::table('media')
            ->where('collection_name', 'default')
            ->whereIn('model_type', ['App\\Models\\UgcPoi', 'App\\Models\\UgcTrack'])
            ->count();

        $this->table(['Metric', 'Value'], [
            ['Original ugc_media records', $ugcMediaCount],
            ['Migrated media records', $migratedCount],
            ['Success rate', $ugcMediaCount > 0 ? round(($migratedCount / $ugcMediaCount) * 100, 2) . '%' : '100%'],
        ]);

        if ($ugcMediaCount === $migratedCount) {
            $this->info('✅ Post-migration verification passed');
            return true;
        } else {
            $this->warn('⚠️ Post-migration verification: Record count mismatch');
            return false;
        }
    }


} 