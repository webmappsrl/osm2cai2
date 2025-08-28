<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Wm\WmPackage\Models\App;

class BuildAppPoisGeojsonCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:build-pois-geojson {app_id : The ID of the app}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Build pois.geojson file for a specific app';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $appId = $this->argument('app_id');
        
        $this->info("Generando pois.geojson per App ID: {$appId}");
        
        // Trova l'app
        $app = App::find($appId);
        if (!$app) {
            $this->error("App con ID {$appId} non trovata!");
            return 1;
        }
        
        $this->info("App trovata: {$app->name}");
        
        // Genera il geojson usando il metodo dell'App
        $geojson = $app->BuildPoisGeojson();
        
        $this->info("✅ File pois.geojson generato con successo per App {$appId}");
        $this->info("📊 Features generate: " . count($geojson['features']));
        return 0;
        
        return 0;
    }
}
