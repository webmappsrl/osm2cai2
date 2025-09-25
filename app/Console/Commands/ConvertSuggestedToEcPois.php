<?php

namespace App\Console\Commands;

use App\Models\EcPoi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Wm\WmPackage\Models\App;
use Wm\WmPackage\Models\TaxonomyPoiType;
use Wm\WmPackage\Services\GeometryComputationService;

class ConvertSuggestedToEcPois extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:convert-suggested-to-ec-pois {--dry-run : Show what would be converted without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert suggested POIs to EcPois';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
        }

        // Disabilita temporaneamente solo l'EcPoiObserver
        $this->info('🔇 EcPoiObserver disabilitato durante la conversione');

        // Trova l'app acquasorgente
        $acquasorgenteApp = App::where('sku', 'it.webmapp.acquasorgente')->first();

        if (! $acquasorgenteApp) {
            $this->error('❌ App Acquasorgente non trovata!');

            return 1;
        }

        $acquasorgenteAppId = $acquasorgenteApp->id;

        $this->info('🚀 Starting conversion of suggested POIs to EcPois...');

        // URL del GeoJSON dei suggeriti dell'App 58
        $geojsonUrl = 'https://geohub.webmapp.it/api/export/taxonomy/geojson/58/overlay.geojson';

        $this->info("📥 Fetching GeoJSON from: {$geojsonUrl}");

        try {
            $response = Http::timeout(60)->get($geojsonUrl);

            if (! $response->successful()) {
                $this->error("❌ Failed to fetch GeoJSON. HTTP Status: {$response->status()}");

                return 1;
            }

            $geojsonData = $response->json();

            if (! $geojsonData || ! isset($geojsonData['features'])) {
                $this->error('❌ Invalid GeoJSON format or no features found');

                return 1;
            }

            $featuresCount = count($geojsonData['features']);
            $this->info("✅ Successfully fetched GeoJSON with {$featuresCount} features");

            if ($isDryRun) {
                $this->info('🔍 DRY RUN: Would process the following features:');
                foreach (array_slice($geojsonData['features'], 0, 5) as $index => $feature) {
                    $name = $feature['properties']['name'] ?? 'no name';
                    $sourceRef = $feature['properties']['source_ref'] ?? 'no source ref';
                    $this->line('   '.($index + 1).". {$name} (Source: {$sourceRef})");
                }
                if ($featuresCount > 5) {
                    $this->line('   ... and '.($featuresCount - 5).' more features');
                }
            } else {
                // TODO: chiamo save quietly e poi creo una custom chein di jobs
                // Salva l'event dispatcher originale
                $originalDispatcher = EcPoi::getEventDispatcher();

                // Crea un nuovo dispatcher senza l'EcPoiObserver per non chiamare $app->buildPoisGeojson() ad ogni poi
                $newDispatcher = new \Illuminate\Events\Dispatcher;
                EcPoi::setEventDispatcher($newDispatcher);

                $taxonomyPoiType = $this->createTaxonomyPoiTypeIfNotExists();

                foreach ($geojsonData['features'] as $feature) {
                    try {
                        DB::beginTransaction();
                        $properties = isset($feature['properties']) ? $feature['properties'] : [];
                        $properties['suggestion'] = [
                            'suggested_id' => $feature['properties']['id'],
                            'conversion_date' => now()->toISOString(),
                        ];
                        // TODO: modificare la descricione con un html custom e migliorato
                        $properties['description'] = isset($properties['popup']['html']) ? ['it' => $properties['popup']['html']] : [];

                        $name = $properties['name'] ?? 'No name';

                        // Converti la geometria GeoJSON in formato WKB
                        $geometry = GeometryComputationService::make()->convertTo3DGeometry($feature['geometry']);

                        $ecPoi = EcPoi::create([
                            'name' => $name,
                            'geometry' => $geometry,
                            'properties' => $properties,
                            'app_id' => $acquasorgenteAppId,
                            'user_id' => $acquasorgenteApp->user_id,
                            'type' => 'natural_spring',
                            'score' => 1,
                        ]);

                        $ecPoi->taxonomyPoiTypes()->attach($taxonomyPoiType->id);

                        DB::commit();

                        $this->line("✅ Converted suggested POI ID {$feature['properties']['id']} to EcPoi ID {$ecPoi->id}");

                    } catch (\Exception $e) {
                        DB::rollBack();
                        $this->error('❌ Error converting suggested POIs: '.$e->getMessage());
                        Log::error('Error converting suggested POIs', [
                            'error' => $e->getMessage(),
                        ]);
                        // Ripristina l'event dispatcher originale
                        EcPoi::setEventDispatcher($originalDispatcher);

                        return 1;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->error('❌ Error fetching GeoJSON: '.$e->getMessage());
            Log::error('Error fetching suggested POIs GeoJSON', [
                'url' => $geojsonUrl,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Ripristina l'event dispatcher originale
            EcPoi::setEventDispatcher($originalDispatcher);

            return 1;
        }

        $this->info('📊 Conversion Summary:');
        $this->info('   - GeoJSON URL: '.$geojsonUrl);
        $this->info('   - Features found: '.($featuresCount ?? 0));
        $this->info('   - Mode: '.($isDryRun ? 'DRY RUN (no actual changes made)' : 'LIVE (changes applied)'));

        $this->newLine();
        $this->info('✅ Suggested POIs conversion completed!');

        // Ripristina l'event dispatcher originale
        EcPoi::setEventDispatcher($originalDispatcher);
        $this->info('🔄 Event dispatcher ripristinato');

        return 0;
    }

    public function createTaxonomyPoiTypeIfNotExists(): TaxonomyPoiType
    {
        $taxonomyPoiType = TaxonomyPoiType::where('identifier', 'water-suggestions')->first();
        if (! $taxonomyPoiType) {
            $taxonomyPoiType = TaxonomyPoiType::create([
                'name' => ['it' => 'Suggerimenti', 'en' => 'Suggestions'],
                'description' => [],
                'excerpt' => [],
                'identifier' => 'water-suggestions',
                'icon' => 'txn-info',
            ]);
        }

        return $taxonomyPoiType;
    }
}
