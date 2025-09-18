<?php

namespace App\Console\Commands;

use App\Models\EcPoi;
use App\Models\UgcPoi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Wm\WmPackage\Models\App;
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
            $existingEcPoi = EcPoi::where('properties->ugc_poi_id', $ugcPoi->id)->first();

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

                    // Assicurati che description e excerpt abbiano la struttura translatable corretta
                    if (! isset($properties['description']) || ! is_array($properties['description'])) {
                        $properties['description'] = [
                            'it' => $properties['description'] ?? 'Sorgente d\'acqua naturale',
                        ];
                    }

                    if (! isset($properties['excerpt']) || ! is_array($properties['excerpt'])) {
                        $properties['excerpt'] = [
                            'it' => $properties['excerpt'] ?? 'Sorgente d\'acqua potabile',
                        ];
                    }

                    // Aggiungi le informazioni UGC dentro l'attributo 'ugc'
                    $properties['ugc'] = [
                        'ugc_poi_id' => $ugcPoi->id,
                        'ugc_user_id' => $ugcPoi->user_id, // ID dell'utente proprietario dell'UgcPoi
                        'conversion_date' => now()->toISOString(),
                    ];

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
}
