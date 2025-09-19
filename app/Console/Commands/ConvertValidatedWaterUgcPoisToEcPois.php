<?php

namespace App\Console\Commands;

use App\Models\EcPoi;
use App\Models\UgcPoi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Wm\WmPackage\Models\App;
use Wm\WmPackage\Models\Media;
use Wm\WmPackage\Models\TaxonomyPoiType;

class ConvertValidatedWaterUgcPoisToEcPois extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:convert-validated-water-ugc-pois-to-ec-pois {--dry-run : Show what would be converted without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert validated UgcPois with form_id "water" to EcPois and associate them with acquasorgente app';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
        }

        // Trova l'app acquasorgente
        $acquasorgenteApp = App::where('sku', 'it.webmapp.acquasorgente')->first();

        if (! $acquasorgenteApp) {
            $this->error('App Acquasorgente non trovata!');

            return 1;
        }

        $acquasorgenteAppId = $acquasorgenteApp->id;

        // Trova tutti gli UgcPoi con form_id 'water' e validated 'valid'
        $waterUgcPois = UgcPoi::where('form_id', 'water')
            ->where('validated', 'valid')
            ->get();

        $this->info("Found {$waterUgcPois->count()} validated water UgcPois");

        if ($waterUgcPois->isEmpty()) {
            $this->warn('No validated water UgcPois found to convert');

            return 0;
        }

        $convertedCount = 0;
        $skippedCount = 0;
        $taxonomyPoiType = $this->createTaxonomyPoiTypeIfNotExists();

        foreach ($waterUgcPois as $ugcPoi) {
            // Controlla se esiste già un EcPoi per questo UgcPoi
            $existingEcPoi = EcPoi::where('properties->ugc->ugc_poi_id', $ugcPoi->id)->first();

            if ($existingEcPoi) {
                $this->line("⏭️  Skipping UgcPoi ID {$ugcPoi->id} - EcPoi already exists (ID: {$existingEcPoi->id})");
                $skippedCount++;
                continue;
            }

            if (! $isDryRun) {
                try {
                    DB::beginTransaction();

                    // Ottieni le properties esistenti dell'UgcPoi
                    $ugcProperties = [];
                    if ($ugcPoi->properties) {
                        $ugcProperties = is_string($ugcPoi->properties) ? json_decode($ugcPoi->properties, true) : $ugcPoi->properties;
                        if (! is_array($ugcProperties)) {
                            $ugcProperties = [];
                        }
                    }

                    // Ottieni anche il raw_data se presente
                    if ($ugcPoi->raw_data) {
                        $rawData = is_string($ugcPoi->raw_data) ? json_decode($ugcPoi->raw_data, true) : $ugcPoi->raw_data;
                        if (is_array($rawData)) {
                            $ugcProperties = array_merge($ugcProperties, $rawData);
                        }
                    }

                    // Prepara le properties finali con informazioni UGC strutturate
                    $properties = $ugcProperties;

                    // Aggiungi le informazioni UGC dentro l'attributo 'ugc'
                    $properties['ugc'] = [
                        'ugc_poi_id' => $ugcPoi->id,
                        'ugc_user_id' => $ugcPoi->user_id, // ID dell'utente proprietario dell'UgcPoi
                        'conversion_date' => now()->toISOString(),
                    ];
                    unset($properties['uuid']);
                    $properties['form']['index'] = 0;

                    // Crea il nuovo EcPoi
                    $ecPoi = EcPoi::create([
                        'name' => $ugcPoi->name ?? 'Sorgente d\'acqua',
                        'geometry' => $ugcPoi->geometry,
                        'properties' => $properties,
                        'app_id' => $acquasorgenteAppId,
                        'user_id' => $acquasorgenteApp->user_id, // Usiamo l'utente detentore dell'app
                        'type' => 'natural_spring',
                        'score' => 1,
                    ]);

                    // Associa la taxonomy_poi_type "Punto acqua" all'EcPoi
                    $ecPoi->taxonomyPoiTypes()->attach($taxonomyPoiType->id);

                    $medias = $ugcPoi->media;
                    if ($medias->count() > 0) {
                        foreach ($medias as $media) {
                            try {
                                $sourceDisk = Storage::disk($media->disk);
                                $sourcePath = $media->getPath();
                                $fileContent = null;
                                $sourceType = '';

                                // Determina la fonte del contenuto del file
                                if ($sourceDisk->exists($sourcePath)) {
                                    // Leggi il contenuto del file originale
                                    $fileContent = $sourceDisk->get($sourcePath);
                                    $sourceType = 'local file';
                                } else {
                                    $this->warn("⚠️  Source file not found: {$sourcePath} - Checking for relative_url");
                                    
                                    // Controlla se esiste un relative_url nelle properties del media
                                    $relativeUrl = null;
                                    
                                    // Controlla nelle custom_properties del media
                                    if ($media->custom_properties) {
                                        $customProps = is_string($media->custom_properties) ? json_decode($media->custom_properties, true) : $media->custom_properties;
                                        $relativeUrl = $customProps['relative_url'] ?? null;
                                    }
                                    
                                    // Se non trovato, controlla nelle properties dell'UgcPoi
                                    if (!$relativeUrl && isset($properties['media'])) {
                                        foreach ($properties['media'] as $mediaItem) {
                                            if (isset($mediaItem['relative_url'])) {
                                                $relativeUrl = $mediaItem['relative_url'];
                                                break;
                                            }
                                        }
                                    }
                                    
                                    // Se trovato un relative_url, scarica l'immagine
                                    if ($relativeUrl) {
                                        $this->line("📥 Downloading image from URL: {$relativeUrl}");
                                        
                                        $fileContent = file_get_contents($relativeUrl);
                                        if ($fileContent === false) {
                                            throw new \Exception("Failed to download image from URL");
                                        }
                                        $sourceType = 'downloaded from URL';
                                    } else {
                                        $this->warn("⚠️  No relative_url found in media properties - Skipping media duplication");
                                        continue;
                                    }
                                }

                                // Se abbiamo il contenuto del file, procedi con la duplicazione
                                if ($fileContent !== null) {
                                    // Crea una copia del record del database
                                    $duplicatedMedia = $media->replicate();
                                    $duplicatedMedia->uuid = (string) Str::uuid();
                                    $duplicatedMedia->model()->associate($ecPoi);

                                    // Salva il nuovo record del media
                                    $duplicatedMedia->save();

                                    // Ottieni il nuovo path per il file duplicato
                                    $newPath = $duplicatedMedia->getPath();

                                    // Scrivi il file fisico nella nuova posizione
                                    $sourceDisk->put($newPath, $fileContent);

                                    $this->line("📁 Copied media file from {$sourceType}: {$media->file_name} -> {$duplicatedMedia->file_name}");
                                }
                            } catch (\Exception $e) {
                                $this->error("❌ Error copying media {$media->id}: ".$e->getMessage());
                                // Continua con il prossimo media anche se questo fallisce
                            }
                        }
                    }

                    DB::commit();

                    $this->line("✅ Converted UgcPoi ID {$ugcPoi->id} to EcPoi ID {$ecPoi->id}");

                    // Log dettagliato delle properties dell'EcPoi creato
                    $this->line('📋 EcPoi Properties:');
                    $this->line("   - ID: {$ecPoi->id}");
                    $this->line("   - Name: {$ecPoi->name}");
                    $this->line("   - Type: {$ecPoi->type}");
                    $this->line("   - App ID: {$ecPoi->app_id}");
                    $this->line("   - User ID: {$ecPoi->user_id}");
                    $this->line("   - Score: {$ecPoi->score}");
                    $this->line('   - Geometry: '.($ecPoi->geometry ? 'Present' : 'Missing'));
                    $this->line("   - Media count: {$ecPoi->media->count()}");

                    $convertedCount++;
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("❌ Error converting UgcPoi ID {$ugcPoi->id}: ".$e->getMessage());
                }
            } else {
                $this->line("🔄 Would convert UgcPoi ID {$ugcPoi->id} to EcPoi");
                $convertedCount++;
            }
        }

        $this->newLine();
        $this->info('📊 Conversion Summary:');
        $this->info("   - Total validated water UgcPois: {$waterUgcPois->count()}");
        $this->info("   - Converted: {$convertedCount}");
        $this->info("   - Skipped (already exists): {$skippedCount}");

        if ($isDryRun) {
            $this->info('   - Mode: DRY RUN (no actual changes made)');
        } else {
            $this->info('   - Mode: LIVE (changes applied)');
        }

        return 0;
    }

    public function createTaxonomyPoiTypeIfNotExists(): TaxonomyPoiType
    {
        $taxonomyPoiType = TaxonomyPoiType::where('identifier', 'water-point')->first();
        if (! $taxonomyPoiType) {
            $taxonomyPoiType = TaxonomyPoiType::create([
                'name' => ['it' => 'Punto acqua', 'en' => 'Water point'],
                'description' => [],
                'excerpt' => [],
                'identifier' => 'water-point',
                'properties' => [
                    'name' => ['it' => 'Punto acqua'],
                    'geohub_id' => 370,
                ],
                'icon' => 'txn-water',
            ]);
        }

        return $taxonomyPoiType;
    }

    /**
     * Guess MIME type from file extension
     */
    private function guessMimeTypeFromExtension(string $extension): string
    {
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'svg' => 'image/svg+xml',
        ];

        return $mimeTypes[strtolower($extension)] ?? 'image/jpeg';
    }
}