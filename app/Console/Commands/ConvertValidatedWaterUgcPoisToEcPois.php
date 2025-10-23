<?php

namespace App\Console\Commands;

use App\Models\EcPoi;
use App\Models\UgcPoi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Wm\WmPackage\Models\App;
use Wm\WmPackage\Models\TaxonomyPoiType;

class ConvertValidatedWaterUgcPoisToEcPois extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:convert-validated-water-ugc-pois-to-ec-pois {--dry-run : Show what would be converted without actually doing it} {--skip-media : Skip downloading and copying media files}';

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
        $skipMedia = $this->option('skip-media');

        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
        }

        if ($skipMedia) {
            $this->info('📷 SKIP MEDIA MODE - Media files will not be downloaded or copied');
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
        $totalCount = $waterUgcPois->count();
        $currentIndex = 0;

        foreach ($waterUgcPois as $ugcPoi) {
            $currentIndex++;
            // Controlla se esiste già un EcPoi per questo UgcPoi
            $existingEcPoi = EcPoi::where('properties->ugc->ugc_poi_id', $ugcPoi->id)->first();

            if ($existingEcPoi) {
                $this->line("({$currentIndex}/{$totalCount}) ⏭️  Skipping UgcPoi ID {$ugcPoi->id} - EcPoi already exists (ID: {$existingEcPoi->id})");
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

                    // name preso da properties form
                    $name = isset($properties['form']['title']) ? $properties['form']['title'] : $ugcPoi->name;
                    $name = $name ?? 'Sorgente d\'acqua';

                    $ecPoi = EcPoi::withoutEvents(function () use ($name, $ugcPoi, $properties, $acquasorgenteAppId, $acquasorgenteApp) {
                        return EcPoi::create([
                            'name' => $name,
                            'geometry' => $ugcPoi->geometry,
                            'properties' => $properties,
                            'app_id' => $acquasorgenteAppId,
                            'user_id' => $acquasorgenteApp->user_id,
                            'type' => 'natural_spring',
                            'score' => 1,
                        ]);
                    });

                    // Associa la taxonomy_poi_type "Punto acqua" all'EcPoi
                    $ecPoi->taxonomyPoiTypes()->attach($taxonomyPoiType->id);

                    $medias = $ugcPoi->media;
                    if ($medias->count() > 0) {
                        if ($skipMedia) {
                            $this->line("📷 Skipping {$medias->count()} media files (--skip-media option)");
                        } else {
                            foreach ($medias as $media) {
                                try {
                                    $sourceDisk = Storage::disk($media->disk);
                                    $fileContent = null;
                                    $sourceType = '';

                                    // Controlla se esiste un relative_url nelle properties del media
                                    $relativeUrl = null;

                                    // Controlla nelle custom_properties del media
                                    if ($media->custom_properties) {
                                        $customProps = is_string($media->custom_properties) ? json_decode($media->custom_properties, true) : $media->custom_properties;
                                        $relativeUrl = $customProps['relative_url'] ?? null;
                                    }

                                    // Se non trovato, controlla nelle properties dell'UgcPoi
                                    if (! $relativeUrl && isset($properties['media'])) {
                                        foreach ($properties['media'] as $mediaItem) {
                                            if (isset($mediaItem['relative_url'])) {
                                                $relativeUrl = $mediaItem['relative_url'];
                                                break;
                                            }
                                        }
                                    }

                                    // Se trovato un relative_url, procedi con la duplicazione
                                    if ($relativeUrl) {
                                        // Se l'URL non contiene geohub.webmapp.it, aggiungi il prefisso osm2cai.cai.it
                                        if (strpos($relativeUrl, 'geohub.webmapp.it') === false) {
                                            $relativeUrl = 'https://osm2cai.cai.it/storage/' . ltrim($relativeUrl, '/');
                                        }

                                        // Controlla se il file esiste già nel sourceDisk
                                        $originalPath = $media->getPath();

                                        if ($sourceDisk->exists($originalPath)) {
                                            // Se il file esiste, usa copy()
                                            $duplicatedMedia = $media->copy($ecPoi);
                                            $this->line("✅ Media file copied: {$media->file_name} -> {$duplicatedMedia->file_name}");
                                        } else {
                                            // Se il file non esiste, scarica da URL usando addMediaFromUrl()
                                            try {
                                                $duplicatedMedia = $ecPoi->addMediaFromUrl($relativeUrl)
                                                    ->usingName($media->name ?? $media->file_name)
                                                    ->usingFileName($media->file_name)
                                                    ->toMediaCollection($media->collection_name);

                                                $this->line("✅ Media file downloaded and saved with APP_ID {$ecPoi->app_id}: {$duplicatedMedia->file_name}");
                                            } catch (\Exception $e) {
                                                $this->error("❌ Error downloading media from URL {$relativeUrl}: " . $e->getMessage());
                                                continue;
                                            }
                                        }
                                    } else {
                                        $this->warn('⚠️  No relative_url found in media properties - Skipping media duplication');
                                        continue;
                                    }
                                } catch (\Exception $e) {
                                    $this->error("❌ Error copying media {$media->id}: " . $e->getMessage());
                                    // Continua con il prossimo media anche se questo fallisce
                                }
                            }
                        }
                    }

                    DB::commit();

                    $this->line("({$currentIndex}/{$totalCount}) ✅ Converted UgcPoi ID {$ugcPoi->id} to EcPoi ID {$ecPoi->id}");

                    $convertedCount++;
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("❌ Error converting UgcPoi ID {$ugcPoi->id}: " . $e->getMessage());
                }
            } else {
                $this->line("({$currentIndex}/{$totalCount}) 🔄 Would convert UgcPoi ID {$ugcPoi->id} to EcPoi");
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

        if ($skipMedia) {
            $this->info('   - Media: SKIPPED (--skip-media option)');
        } else {
            $this->info('   - Media: PROCESSED');
        }

        return 0;
    }

    public function createTaxonomyPoiTypeIfNotExists(): TaxonomyPoiType
    {
        $taxonomyPoiType = TaxonomyPoiType::where('identifier', 'water-monitoring')->first();
        if (! $taxonomyPoiType) {
            $taxonomyPoiType = TaxonomyPoiType::create([
                'name' => ['it' => 'Monitoraggio acqua', 'en' => 'Water monitoring'],
                'description' => [],
                'excerpt' => [],
                'identifier' => 'water-monitoring',
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
