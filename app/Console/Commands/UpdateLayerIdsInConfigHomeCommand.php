<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Wm\WmPackage\Models\App;
use Wm\WmPackage\Models\Layer;

class UpdateLayerIdsInConfigHomeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:update-layer-ids-config-home
        {--app= : ID specifico dell\'app da processare}
        {--dry-run : Mostra cosa verrebbe aggiornato senza salvare}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aggiorna gli ID dei layer nella conf_home delle app';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Aggiornamento ID layer nella conf_home...');

        $appId = $this->option('app');
        $dryRun = $this->option('dry-run');

        // Recupera le app
        $apps = $appId ? App::where('id', $appId)->get() : App::all();

        if ($apps->isEmpty()) {
            $this->error('Nessuna app trovata.');

            return 1;
        }

        $totalUpdated = 0;
        $totalApps = 0;

        foreach ($apps as $app) {
            $this->info("📱 Processando App ID: {$app->id} - {$app->customer_name}");

            $updated = $this->updateAppConfigHome($app, $dryRun);

            if ($updated) {
                $totalUpdated++;
            }
            $totalApps++;
        }

        if ($dryRun) {
            $this->info("🔍 DRY RUN - {$totalUpdated}/{$totalApps} app avrebbero bisogno di aggiornamenti");
        } else {
            $this->info("✅ Completato! {$totalUpdated}/{$totalApps} app aggiornate");
        }

        return 0;
    }

    /**
     * Aggiorna la conf_home di un'app
     */
    private function updateAppConfigHome(App $app, bool $dryRun): bool
    {
        // Usa getRawOriginal per evitare il cast FlexibleCast
        $configHome = $app->getRawOriginal('config_home');

        $this->line('  🔍 Debug - Config home raw: '.var_export($configHome, true));

        // Se config_home è null o vuoto, non c'è nulla da aggiornare
        if (empty($configHome)) {
            $this->line('  ⚠️  Config home vuota, saltata');

            return false;
        }

        // Gestisci config_home come nell'AppConfigService
        if (! empty($configHome)) {
            if (is_string($configHome)) {
                $configHome = json_decode($configHome, true);
            } elseif (is_array($configHome)) {
                // Se config_home è già un array, lo usiamo direttamente
                // $configHome rimane invariato
            } elseif (is_object($configHome) && method_exists($configHome, 'toArray')) {
                // Gestisci oggetti Collection di NovaFlexibleContent
                $configHome = $configHome->toArray();
            }
        }

        // Se non è un array valido, non c'è nulla da aggiornare
        if (! is_array($configHome) || ! isset($configHome['HOME'])) {
            $this->line('  ⚠️  Config home non valida, saltata');

            return false;
        }

        $homeElements = $configHome['HOME'] ?? [];
        $updated = false;
        $updates = [];

        // Recupera tutti i layer dell'app (diretti e associati)
        $allLayers = $app->layers()->get()->concat($app->associatedLayers()->get());

        foreach ($homeElements as $index => $element) {
            // Cerca elementi di tipo 'layer' o che hanno un campo 'layer'
            if (isset($element['box_type']) && $element['box_type'] === 'layer') {
                $layerId = $element['layer'];
                // Nelle Collection Laravel non funziona l'operatore JSON "->"; usare dot notation
                $layer = $allLayers->firstWhere('properties.geohub_id', $layerId);
                if ($layer) {
                    $element['layer'] = $layer->id;
                    $homeElements[$index] = $element;
                    $updated = true;
                    $updates[] = "Aggiornato layer per elemento {$index}";
                }
            }
        }

        // Rimuovi indici vuoti
        $homeElements = array_values($homeElements);

        if ($updated) {
            $configHome['HOME'] = $homeElements;

            if ($dryRun) {
                $this->line('  🔍 DRY RUN - Modifiche che verrebbero applicate:');
                foreach ($updates as $update) {
                    $this->line("    - {$update}");
                }
            } else {
                // Salva direttamente via DB per evitare che Laravel provi a settare chiavi top-level (es. HOME)
                DB::table('apps')
                    ->where('id', $app->id)
                    ->update(['config_home' => json_encode($configHome)]);

                $this->line('  ✅ Config home aggiornata:');
                foreach ($updates as $update) {
                    $this->line("    - {$update}");
                }
            }
        } else {
            $this->line('  ✓ Config home già aggiornata');
        }

        return $updated;
    }
}
