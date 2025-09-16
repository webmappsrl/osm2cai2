<?php

namespace App\Console\Commands;

use App\Models\EcPoi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Wm\WmPackage\Models\TaxonomyPoiType;

class CreateWaterPoiTypeTaxonomyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-water-poi-type-taxonomy {--dry-run : Show what would be created without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create taxonomy_poi_types for "Punto acqua" and associate it with all ec_pois';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
        }

        // Verifica se la taxonomy_poi_types "Punto acqua" esiste già
        $existingPoiType = TaxonomyPoiType::whereRaw("name::jsonb->>'it' = ?", ['Punto acqua'])->first();

        if ($existingPoiType) {
            $this->info("✅ Taxonomy POI Type 'Punto acqua' esiste già (ID: {$existingPoiType->id})");
            $poiTypeId = $existingPoiType->id;
        } else {
            // Crea la taxonomy_poi_types "Punto acqua"
            $this->info("📝 Creazione taxonomy_poi_types 'Punto acqua'...");

            if (! $isDryRun) {
                $poiType = TaxonomyPoiType::create([
                    'name' => ['it' => 'Punto acqua'],
                    'description' => [],
                    'excerpt' => [],
                    'identifier' => null,
                    'properties' => [
                        'name' => ['it' => 'Punto acqua'],
                        'geohub_id' => 370,
                        'icon' => 'txn-water',
                    ],
                    'icon' => 'txn-water',
                ]);

                $poiTypeId = $poiType->id;
                $this->info("✅ Taxonomy POI Type 'Punto acqua' creata con ID: {$poiTypeId}");
            } else {
                $this->info("🔍 DRY RUN: Taxonomy POI Type 'Punto acqua' sarebbe creata");
                $poiTypeId = 'DRY_RUN_ID';
            }
        }

        // Trova tutti gli ec_pois esistenti
        $ecPoisCount = EcPoi::count();
        $this->info("📊 Trovati {$ecPoisCount} ec_pois nel database");

        if ($ecPoisCount === 0) {
            $this->warn('⚠️  Nessun ec_poi trovato nel database');

            return 0;
        }

        // Associa la taxonomy_poi_types a tutti gli ec_pois
        $this->info('🔗 Associazione taxonomy_poi_types a tutti gli ec_pois...');

        if (! $isDryRun) {
            // Inserisci le associazioni in batch per evitare il limite di parametri PostgreSQL
            $batchSize = 1000; // Batch di 1000 record alla volta
            $ecPoiIds = EcPoi::pluck('id')->toArray();
            $totalBatches = ceil(count($ecPoiIds) / $batchSize);

            $this->info("📦 Inserimento in {$totalBatches} batch da {$batchSize} record ciascuno...");

            for ($i = 0; $i < count($ecPoiIds); $i += $batchSize) {
                $batch = array_slice($ecPoiIds, $i, $batchSize);
                $insertData = [];

                foreach ($batch as $ecPoiId) {
                    $insertData[] = [
                        'taxonomy_poi_type_id' => $poiTypeId,
                        'taxonomy_poi_typeable_id' => $ecPoiId,
                        'taxonomy_poi_typeable_type' => 'App\\Models\\EcPoi',
                    ];
                }

                DB::table('taxonomy_poi_typeables')->insert($insertData);

                $currentBatch = floor($i / $batchSize) + 1;
                $this->info("✅ Batch {$currentBatch}/{$totalBatches} completato (".count($batch).' record)');
            }

            $this->info("✅ Associazione completata per {$ecPoisCount} ec_pois");
        } else {
            $this->info("🔍 DRY RUN: {$ecPoisCount} ec_pois sarebbero associati alla taxonomy_poi_types");
        }

        // Verifica finale
        if (! $isDryRun) {
            $associatedCount = DB::table('taxonomy_poi_typeables')
                ->where('taxonomy_poi_type_id', $poiTypeId)
                ->where('taxonomy_poi_typeable_type', 'App\\Models\\EcPoi')
                ->count();

            $this->info("🔍 Verifica finale: {$associatedCount} ec_pois associati alla taxonomy_poi_types 'Punto acqua'");
        }

        $this->info('✅ Comando completato con successo!');

        return 0;
    }
}
