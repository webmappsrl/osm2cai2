<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\HikingRoute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class UpdateHikingRoutesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai2:update-hiking-routes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates hiking routes by checking the latest updated_at timestamp and fetching updated data from the API.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $logger = Log::channel('hiking-routes-update');

        // Recupera il valore updated_at più recente dalla tabella hiking_routes
        $latestUpdatedAt = '2024-08-14 09:30:52';

        if (!$latestUpdatedAt) {
            $this->error('No hiking routes found in the database.');
            $logger->error('No hiking routes found in the database.');
            return;
        }

        // Converte la data nel formato richiesto dall'API
        $formattedUpdatedAt = Carbon::parse($latestUpdatedAt)->toIso8601String();
        $apiUrl = 'https://osmfeatures.maphub.it/api/v1/features/hiking-routes/list';

        // Effettua la chiamata all'API con paginazione
        $page = 1;
        $routes = [];
        do {
            $response = Http::get($apiUrl, [
                'updated_at' => $formattedUpdatedAt,
                'page' => $page
            ]);

            if ($response->failed()) {
                $this->error('API request failed: ' . $response->body());
                $logger->error('API request failed: ' . $response->body());
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
            $this->info("Processing hiking route with ID: $osmfeaturesId");
            $logger->info("Processing hiking route with ID: $osmfeaturesId");

            // Effettua la chiamata all'API per ottenere i dati dettagliati del singolo hiking route
            $detailApiUrl = "https://osmfeatures.maphub.it/api/v1/features/hiking-routes/{$osmfeaturesId}";
            $detailResponse = Http::get($detailApiUrl);

            if ($detailResponse->failed()) {
                $this->error("Failed to fetch details for hiking route ID: $osmfeaturesId");
                $logger->error("Failed to fetch details for hiking route ID: $osmfeaturesId");
                continue;
            }

            $hikingRouteData = $detailResponse->json();

            // Aggiorna il modello HikingRoute con i dati ottenuti
            $hikingRoute = HikingRoute::where('osmfeatures_id', $osmfeaturesId)->first();

            if ($hikingRoute) {
                $hikingRoute->update([
                    'updated_at' => Carbon::parse($route['updated_at'])->toDateTimeString(),
                    'osmfeatures_data' => json_encode($hikingRouteData),
                    'geometry' => DB::select("SELECT ST_AsText(ST_GeomFromGeoJSON('" . json_encode($hikingRouteData['geometry']) . "'))")[0]->st_astext
                ]);

                $this->info("Hiking route with ID: $osmfeaturesId updated successfully.");
                $logger->info("Hiking route with ID: $osmfeaturesId updated successfully.");
            } else {
                $this->error("Hiking route with ID: $osmfeaturesId not found in the database.");
                $logger->error("Hiking route with ID: $osmfeaturesId not found in the database.");
            }
        }

        $logger->info('Finished updating hiking routes.');
    }
}
