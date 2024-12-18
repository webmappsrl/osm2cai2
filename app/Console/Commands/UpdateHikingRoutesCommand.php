<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdateHikingRoutesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:update-hiking-routes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates hiking routes by checking the latest updated_at timestamp and fetching updated data from osmfeatures API.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $logger = Log::channel('hiking-routes-update');

        // Recupera il valore updated_at più recente dalla tabella hiking_routes
        $latestUpdatedAt = HikingRoute::max('osmfeatures_updated_at');

        if (! $latestUpdatedAt) {
            $errormsg = 'No hiking routes found in the database.';
            $this->error($errormsg);
            $logger->error($errormsg);

            return;
        }

        // Converte la data nel formato richiesto dall'API
        $formattedUpdatedAt = Carbon::parse($latestUpdatedAt)->toIso8601String();
        $endpoint = HikingRoute::getOsmfeaturesEndpoint();
        $apiUrl = $endpoint.'list';

        // Effettua la chiamata all'API con paginazione
        $page = 1;
        $routes = [];
        do {
            $response = Http::get($apiUrl, [
                'updated_at' => $formattedUpdatedAt,
                'page' => $page,
            ]);

            if ($response->failed()) {
                $errormsg = 'API request failed: '.$response->body();
                $this->error($errormsg);
                $logger->error($errormsg);

                return;
            }

            $data = $response->json();
            $routes = array_merge($routes, $data['data'] ?? []);
            $page++;
        } while ($data['current_page'] < $data['last_page']);

        if (empty($routes)) {
            $this->info('No new hiking routes to update.');
            $logger->info('No new hiking routes to update.');

            return;
        }

        // Processa ogni hiking route ritornata dall'API
        foreach ($routes as $route) {
            $osmfeaturesId = $route['id'];
            $logmsg = "Processing hiking route with ID: $osmfeaturesId";
            $this->info($logmsg);
            $logger->info($logmsg);

            // Effettua la chiamata all'API per ottenere i dati dettagliati del singolo hiking route
            $detailApiUrl = $endpoint.$osmfeaturesId;
            $detailResponse = Http::get($detailApiUrl);

            if ($detailResponse->failed()) {
                $errormsg = "Failed to fetch details for hiking route ID: $osmfeaturesId";
                $this->error($errormsg);
                $logger->error($errormsg);
                continue;
            }

            $hikingRouteData = $detailResponse->json();

            // Aggiorna il modello HikingRoute con i dati ottenuti
            $hikingRoute = HikingRoute::where('osmfeatures_id', $osmfeaturesId)->first();

            if ($hikingRoute) {
                $hikingRoute->update([
                    'osmfeatures_updated_at' => Carbon::parse($route['updated_at'])->toDateTimeString(),
                    'osmfeatures_data' => json_encode($hikingRouteData),
                    'geometry' => DB::select("SELECT ST_AsText(ST_GeomFromGeoJSON('".json_encode($hikingRouteData['geometry'])."'))")[0]->st_astext,
                ]);
                $logMessage = "Hiking route with ID: $osmfeaturesId updated successfully.";
                $this->info($logMessage);
                $logger->info($logMessage);
            } else {
                $failMessage = "Hiking route with ID: $osmfeaturesId not found in the database.";
                $this->error($failMessage);
                $logger->error($failMessage);
            }
        }

        // Store the current timestamp in cache
        Cache::forever('last_osm_sync', now()->toDateTimeString());

        $logger->info('Finished updating hiking routes.');
    }
}
