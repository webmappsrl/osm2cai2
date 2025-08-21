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

        // Cerca l'app per SKU it.webmapp.osm2cai
        $app = App::where('sku', 'it.webmapp.osm2cai')->first();

        if (! $app) {
            // Fallback: cerca l'app sentierista per nome
            $app = App::where('name', 'LIKE', '%sentierista%')->first();
        }

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
                ->where('properties->osm2cai_status', $i)
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

        $query = HikingRoute::where('osm2cai_status', $status);
        $totalCount = $query->count();

        if ($totalCount === 0) {
            $this->info("   📭 Nessuna hiking route trovata con status {$status}");

            return;
        }

        $this->info("   📊 Trovate {$totalCount} hiking routes");

        if ($isDryRun) {
            $this->info("   🧪 [DRY RUN] Avrei associato {$totalCount} hiking routes al layer {$layer->id}");

            return;
        }

        // Rimuovi associazioni esistenti per questo layer
        $this->info('   🧹 Pulizia associazioni esistenti...');
        DB::table('layerables')
            ->where('layer_id', $layer->id)
            ->where('layerable_type', HikingRoute::class)
            ->delete();
        $this->info('   🧹 Pulizia completata.');

        // Crea le nuove associazioni in batch con una progress bar
        $this->info('   ➕ Creazione nuove associazioni...');
        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $query->select('id')->orderBy('id')->chunk(500, function ($hikingRoutes) use ($layer, $bar) {
            $insertData = [];
            foreach ($hikingRoutes as $hr) {
                $insertData[] = [
                    'layer_id' => $layer->id,
                    'layerable_id' => $hr->id,
                    'layerable_type' => HikingRoute::class,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (! empty($insertData)) {
                DB::table('layerables')->insert($insertData);
                $bar->advance(count($insertData));
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info("   ✅ Associate {$totalCount} hiking routes al layer per stato {$status}");
    }
}
