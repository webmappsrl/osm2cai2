<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateHikingRoutesAppId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:update-hiking-routes-app-id {--app=1 : ID dell\'app da assegnare}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aggiorna l\'app_id per tutti gli hiking routes esistenti che non hanno un app_id';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $appId = $this->option('app');
        
        $this->info("🔄 Aggiornamento app_id per hiking routes esistenti...");
        $this->info("📱 App ID target: $appId");
        
        // Conta quanti hiking routes non hanno app_id
        $totalWithoutAppId = DB::table('hiking_routes')
            ->whereNull('app_id')
            ->count();
            
        if ($totalWithoutAppId === 0) {
            $this->info("✅ Tutti gli hiking routes hanno già un app_id assegnato");
            return 0;
        }
        
        $this->info("📊 Trovati $totalWithoutAppId hiking routes senza app_id");
        
        // Conferma l'operazione
        if (!$this->confirm("Procedere con l'aggiornamento di $totalWithoutAppId hiking routes?")) {
            $this->info("❌ Operazione annullata");
            return 0;
        }
        
        // Aggiorna tutti gli hiking routes che non hanno app_id
        $updated = DB::table('hiking_routes')
            ->whereNull('app_id')
            ->update(['app_id' => $appId]);
        
        $this->info("✅ Aggiornati $updated hiking routes con app_id = $appId");
        
        // Verifica finale
        $remainingWithoutAppId = DB::table('hiking_routes')
            ->whereNull('app_id')
            ->count();
            
        if ($remainingWithoutAppId === 0) {
            $this->info("🎉 Tutti gli hiking routes hanno ora un app_id assegnato!");
        } else {
            $this->warn("⚠️  Rimangono $remainingWithoutAppId hiking routes senza app_id");
        }
        
        return 0;
    }
}
