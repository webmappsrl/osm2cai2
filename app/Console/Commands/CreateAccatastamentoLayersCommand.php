<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Wm\WmPackage\Models\App;
use Wm\WmPackage\Models\Layer;

class CreateAccatastamentoLayersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:create-accatastamento-layers 
        {--app= : ID dell\'app (default: sentierista app)}
        {--force : Sovrascrivi i layer esistenti}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crea i layer per gli stati di accatastamento (1, 2, 3, 4)';

    /**
     * Layer configurations
     */
    private $layerConfigs = [
        1 => [
            'name' => ['it' => 'Stato Accatastamento 1', 'en' => 'Cadastral State 1'],
            'color' => '#F2C511', // Giallo
            'description' => ['it' => 'Sentieri con stato di accatastamento 1', 'en' => 'Trails with cadastral state 1'],
            'rank' => 1,
            'feature_image' => 'https://ecmedia.s3.eu-central-1.amazonaws.com/EcMedia/6487.png',
        ],
        2 => [
            'name' => ['it' => 'Stato Accatastamento 2', 'en' => 'Cadastral State 2'],
            'color' => '#8E43AD', // Viola
            'description' => ['it' => 'Sentieri con stato di accatastamento 2', 'en' => 'Trails with cadastral state 2'],
            'rank' => 2,
            'feature_image' => 'https://ecmedia.s3.eu-central-1.amazonaws.com/EcMedia/6488.png',
        ],
        3 => [
            'name' => ['it' => 'Stato Accatastamento 3', 'en' => 'Cadastral State 3'],
            'color' => '#2980B9', // Blu
            'description' => ['it' => 'Sentieri con stato di accatastamento 3', 'en' => 'Trails with cadastral state 3'],
            'rank' => 3,
            'feature_image' => 'https://ecmedia.s3.eu-central-1.amazonaws.com/EcMedia/6489.png',
        ],
        4 => [
            'name' => ['it' => 'Stato Accatastamento 4', 'en' => 'Cadastral State 4'],
            'color' => '#27AF60', // Verde
            'description' => ['it' => 'Sentieri con stato di accatastamento 4', 'en' => 'Trails with cadastral state 4'],
            'rank' => 4,
            'feature_image' => 'https://ecmedia.s3.eu-central-1.amazonaws.com/EcMedia/6490.png',
        ],
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🏗️  Creazione layer per stati di accatastamento...');

        // Trova l'app
        $app = $this->findApp();
        if (! $app) {
            $this->error('❌ App non trovata');

            return;
        }

        $this->info("📱 App trovata: {$app->name} (ID: {$app->id})");

        $force = $this->option('force');
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($this->layerConfigs as $status => $config) {
            $result = $this->createOrUpdateLayer($app, $status, $config, $force);

            switch ($result['action']) {
                case 'created':
                    $created++;
                    break;
                case 'updated':
                    $updated++;
                    break;
                case 'skipped':
                    $skipped++;
                    break;
            }
        }

        $this->info('');
        $this->info('📊 Riepilogo:');
        $this->info("   ✅ Creati: {$created}");
        $this->info("   🔄 Aggiornati: {$updated}");
        $this->info("   ⏭️  Saltati: {$skipped}");
        $this->info('');
        $this->info('✅ Operazione completata!');
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
     * Crea o aggiorna un layer per lo stato specificato
     */
    private function createOrUpdateLayer(App $app, int $status, array $config, bool $force): array
    {
        // Cerca layer esistente
        $existingLayer = Layer::where('app_id', $app->id)
            ->where('properties->osm2cai_status', $status)
            ->first();

        if ($existingLayer && ! $force) {
            $name = is_array($existingLayer->name) ? $existingLayer->name['it'] ?? $existingLayer->name['en'] : $existingLayer->name;
            $this->warn("⏭️  Layer per stato {$status} già esistente: {$name} (ID: {$existingLayer->id})");

            return ['action' => 'skipped', 'layer' => $existingLayer];
        }

        $properties = [
            'title' => $config['name'],
            'color' => $config['color'],
            'osm2cai_status' => $status,
            'description' => $config['description'],
        ];

        if ($existingLayer && $force) {
            // Aggiorna layer esistente
            $existingLayer->update([
                'name' => $config['name'],
                'properties' => $properties,
            ]);

            // Aggiungo il media per il layer esistente
            $this->handleFeatureImage($existingLayer, $config);

            $name = is_array($existingLayer->name) ? $existingLayer->name['it'] ?? $existingLayer->name['en'] : $existingLayer->name;
            $this->info("🔄 Aggiornato layer per stato {$status}: {$name} (ID: {$existingLayer->id})");

            return ['action' => 'updated', 'layer' => $existingLayer];
        }

        // Crea nuovo layer
        $layer = new Layer;
        $layer->name = $config['name'];
        $layer->app_id = $app->id;
        $layer->user_id = $app->user_id;
        $layer->rank = $config['rank'];
        $layer->properties = $properties;
        $layer->save();

        // Aggiungo il media per il nuovo layer
        $this->handleFeatureImage($layer, $config);

        $name = is_array($layer->name) ? $layer->name['it'] ?? $layer->name['en'] : $layer->name;
        $this->info("✅ Creato layer per stato {$status}: {$name} (ID: {$layer->id})");

        return ['action' => 'created', 'layer' => $layer];
    }

    /**
     * Gestisce l'aggiunta del media tramite feature_image del config
     */
    private function handleFeatureImage(Layer $layer, array $config): void
    {
        if (! isset($config['feature_image']) || empty($config['feature_image'])) {
            return;
        }

        $featureImageUrl = $config['feature_image'];
        $layerName = is_array($layer->name) ? $layer->name['it'] ?? $layer->name['en'] : $layer->name;

        try {
            // Cancella tutti i media esistenti nella collezione 'default'
            $existingMedia = $layer->getMedia('default');
            if ($existingMedia->isNotEmpty()) {
                $layer->clearMediaCollection('default');
            }

            // Aggiungi il nuovo media dal URL
            $layer->addMediaFromUrl($featureImageUrl)
                ->toMediaCollection('default');
        } catch (\Exception $e) {
            $this->error("❌ Errore nell'aggiunta del media per layer {$layerName}: ".$e->getMessage());
        }
    }
}
