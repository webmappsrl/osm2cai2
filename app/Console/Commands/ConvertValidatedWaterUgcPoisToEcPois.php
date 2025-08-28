<?php

namespace App\Console\Commands;

use App\Models\EcPoi;
use App\Models\UgcPoi;
use Wm\WmPackage\Models\App;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
        $acquasorgenteApp = App::where('sku', '"it.webmapp.acquasorgente"')->first();
        
        if (!$acquasorgenteApp) {
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

        foreach ($waterUgcPois as $ugcPoi) {
            // Controlla se esiste già un EcPoi per questo UgcPoi
            $existingEcPoi = EcPoi::where('properties->ugc_poi_id', $ugcPoi->id)->first();
            
            if ($existingEcPoi) {
                $this->line("⏭️  Skipping UgcPoi ID {$ugcPoi->id} - EcPoi already exists (ID: {$existingEcPoi->id})");
                $skippedCount++;
                continue;
            }

            if (!$isDryRun) {
                try {
                    DB::beginTransaction();

                    // Crea il nuovo EcPoi
                    $ecPoi = EcPoi::create([
                        'name' => $ugcPoi->name ?? 'Sorgente d\'acqua',
                        'geometry' => $ugcPoi->geometry,
                        'properties' => json_encode([
                            'ugc_poi_id' => $ugcPoi->id,
                            'ugc_user_id' => $ugcPoi->user_id, // ID dell'utente proprietario dell'UgcPoi
                            'form_id' => $ugcPoi->form_id,
                            'description' => $ugcPoi->description,
                            'raw_data' => $ugcPoi->raw_data,
                            'converted_from_ugc' => true,
                            'conversion_date' => now()->toISOString(),
                        ]),
                        'app_id' => $acquasorgenteAppId,
                        'user_id' => $acquasorgenteApp->user_id, // Usiamo l'utente detentore dell'app
                        'type' => 'natural_spring',
                        'score' => 1,
                    ]);

                    DB::commit();
                    
                    $this->line("✅ Converted UgcPoi ID {$ugcPoi->id} to EcPoi ID {$ecPoi->id}");
                    $convertedCount++;
                    
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->error("❌ Error converting UgcPoi ID {$ugcPoi->id}: " . $e->getMessage());
                }
            } else {
                $this->line("🔄 Would convert UgcPoi ID {$ugcPoi->id} to EcPoi");
                $convertedCount++;
            }
        }

        $this->newLine();
        $this->info("📊 Conversion Summary:");
        $this->info("   - Total validated water UgcPois: {$waterUgcPois->count()}");
        $this->info("   - Converted: {$convertedCount}");
        $this->info("   - Skipped (already exists): {$skippedCount}");
        
        if ($isDryRun) {
            $this->info("   - Mode: DRY RUN (no actual changes made)");
        } else {
            $this->info("   - Mode: LIVE (changes applied)");
        }

        return 0;
    }
}
