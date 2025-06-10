<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Wm\WmPackage\Models\App;
use Wm\WmPackage\Models\Layer;

class AssociateHikingRoutesToLayersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:associate-hiking-routes-to-layers 
        {--status= : Solo un specifico osm2cai_status (1, 2, 3, 4)}
        {--app= : ID dell\'app (default: sentierista app)}
        {--dry-run : Esegui senza salvare le modifiche}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Associa le hiking routes ai layer corrispondenti basandosi su osm2cai_status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Inizio associazione hiking routes ai layer...');

        // Trova l'app
        $app = $this->findApp();
        if (! $app) {
            $this->error('❌ App non trovata');

            return;
        }

        $this->info("📱 App trovata: {$app->name} (ID: {$app->id})");

        // Trova i layer per gli stati di accatastamento
        $layers = $this->getLayers($app);
        if (empty($layers)) {
            $this->error('❌ Nessun layer per gli stati di accatastamento trovato');

            return;
        }

        $this->displayLayers($layers);

        // Processa le hiking routes
        $statusFilter = $this->option('status');
        $isDryRun = $this->option('dry-run');

        if ($statusFilter) {
            $this->processStatus((int) $statusFilter, $layers, $isDryRun);
        } else {
            // Processa tutti gli stati (1, 2, 3, 4)
            foreach ([1, 2, 3, 4] as $status) {
                $this->processStatus($status, $layers, $isDryRun);
            }
        }

        $this->info('✅ Associazione completata!');
    }

    /**
     * Trova l'app sentierista
     */
    private function findApp(): ?App
    {
        $appId = $this->option('app');

        if ($appId) {
            return App::find($appId);
        }

        // Cerca l'app sentierista
        $app = App::where('name', 'LIKE', '%sentierista%')->first();

        if (! $app) {
            // Fallback: cerca un'app generica
            $app = App::where('name', 'LIKE', '%app%')->first();
        }

        return $app;
    }

    /**
     * Ottieni i layer per gli stati di accatastamento
     */
    private function getLayers(App $app): array
    {
        $layers = [];

        for ($i = 1; $i <= 4; $i++) {
            $layer = Layer::where('app_id', $app->id)
                ->where('properties->stato_accatastamento', $i)
                ->first();

            if ($layer) {
                $layers[$i] = $layer;
            }
        }

        return $layers;
    }

    /**
     * Mostra i layer trovati
     */
    private function displayLayers(array $layers)
    {
        $this->info('📋 Layer trovati:');
        foreach ($layers as $status => $layer) {
            $name = is_array($layer->name) ? $layer->name['it'] ?? $layer->name['en'] ?? 'N/A' : $layer->name;
            $this->line("   Stato {$status}: {$name} (ID: {$layer->id})");
        }
    }

    /**
     * Processa un singolo stato
     */
    private function processStatus(int $status, array $layers, bool $isDryRun)
    {
        if (! isset($layers[$status])) {
            $this->warn("⚠️  Layer per stato {$status} non trovato, salto...");

            return;
        }

        $layer = $layers[$status];

        $this->info("🔄 Processando hiking routes con osm2cai_status = {$status}");

        // Trova le hiking routes con questo status
        $hikingRoutes = HikingRoute::where('osm2cai_status', $status)->get();

        if ($hikingRoutes->isEmpty()) {
            $this->info("   📭 Nessuna hiking route trovata con status {$status}");

            return;
        }

        $this->info("   📊 Trovate {$hikingRoutes->count()} hiking routes");

        if ($isDryRun) {
            $this->info("   🧪 [DRY RUN] Avrei associato {$hikingRoutes->count()} hiking routes al layer {$layer->id}");

            return;
        }

        // Rimuovi associazioni esistenti per questo layer
        $existingCount = DB::table('layerables')
            ->where('layer_id', $layer->id)
            ->where('layerable_type', HikingRoute::class)
            ->count();

        if ($existingCount > 0) {
            DB::table('layerables')
                ->where('layer_id', $layer->id)
                ->where('layerable_type', HikingRoute::class)
                ->delete();
            $this->info("   🧹 Rimosse {$existingCount} associazioni esistenti");
        }

        // Crea le nuove associazioni
        $insertData = [];
        foreach ($hikingRoutes as $hr) {
            $insertData[] = [
                'layer_id' => $layer->id,
                'layerable_id' => $hr->id,
                'layerable_type' => HikingRoute::class,
                'properties' => '{}', // JSON vuoto per rispettare il vincolo NOT NULL
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Inserisci in batch per performance
        $chunks = array_chunk($insertData, 1000);
        foreach ($chunks as $chunk) {
            DB::table('layerables')->insert($chunk);
        }

        $this->info("   ✅ Associate {$hikingRoutes->count()} hiking routes al layer per stato {$status}");
    }
}
