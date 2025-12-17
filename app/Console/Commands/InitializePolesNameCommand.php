<?php

namespace App\Console\Commands;

use App\Http\Clients\NominatimClient;
use App\Models\Poles;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InitializePolesNameCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'poles:initialize-name
                           {--chunk=100 : Number of records to process at once}
                           {--dry-run : Run without actually updating the database}
                           {--limit= : Limit the number of records to process (useful for testing)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inizializza il campo name dei Poles: controlla se ha valore, altrimenti prova da PoleOsmTags.name, altrimenti chiama Nominatim';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        if ($dryRun) {
            $this->warn('DRY RUN MODE: nessuna modifica verrà salvata nel database');
        }

        $this->info('Inizio inizializzazione campo name per i Poles...');

        $query = Poles::where(function ($q) {
            $q->whereNull('name')->orWhere('name', '');
        })
            ->orderBy('id', 'desc'); // Inizia dall'ultimo palo creato

        if ($limit) {
            $query->limit($limit);
            $this->info("Modalità test: processerò solo i primi {$limit} record (dall'ultimo creato).");
        }

        $totalRecords = $query->count();

        if ($totalRecords === 0) {
            $this->warn('Nessun record trovato con name vuoto o null.');

            return Command::SUCCESS;
        }

        // Mostra l'ID più alto che verrà processato
        $highestId = Poles::where(function ($q) {
            $q->whereNull('name')->orWhere('name', '');
        })
            ->orderBy('id', 'desc')
            ->value('id');

        $this->info("Trovati {$totalRecords} record da processare.");
        $this->info("ID più alto da processare: {$highestId}");
        $this->newLine();

        $processedCount = 0;
        $updatedFromOsmTags = 0;
        $updatedFromNominatim = 0;
        $skippedNoGeometry = 0;
        $errorCount = 0;

        $nominatimClient = new NominatimClient;

        // Processa i record uno alla volta (senza chunk) così può essere fermato manualmente
        foreach ($query->get() as $pole) {
            try {
                $this->line("Pole ID = {$pole->id}:");

                // Controlla se name ha già un valore
                if (! empty($pole->name)) {
                    $this->line("  ✓ name già presente: '{$pole->name}'");
                    Log::info('Pole name already set', ['pole_id' => $pole->id, 'name' => $pole->name]);
                    $processedCount++;
                    continue;
                }

                $name = null;
                $source = null;

                // Prova a prendere il valore da PoleOsmTags.name (osmfeatures_data['properties']['osm_tags']['name'])
                if ($pole->osmfeatures_data && isset($pole->osmfeatures_data['properties']['osm_tags']['name'])) {
                    $name = $pole->osmfeatures_data['properties']['osm_tags']['name'];
                    if (! empty($name)) {
                        $source = 'OsmTags';
                        $this->info("  ✓ Recuperato da OsmTags: '{$name}'");
                        Log::info('Pole name retrieved from OsmTags', [
                            'pole_id' => $pole->id,
                            'name' => $name,
                            'dry_run' => $dryRun,
                        ]);
                        if (! $dryRun) {
                            $pole->updateQuietly(['name' => $name]);
                        } else {
                            $this->line("  [DRY-RUN] Verrebbe aggiornato: name = '{$name}'");
                        }
                        $updatedFromOsmTags++;
                        $processedCount++;
                        continue;
                    } else {
                        $this->line("  - OsmTags.name presente ma vuoto");
                        Log::info('Pole OsmTags.name is empty', ['pole_id' => $pole->id]);
                    }
                } else {
                    $this->line("  - OsmTags.name non disponibile");
                    Log::info('Pole OsmTags.name not available', ['pole_id' => $pole->id]);
                }

                // Se anche questo non ha valore, prova con Nominatim
                if (empty($name)) {
                    $coordinates = $this->getCoordinatesFromPole($pole);

                    if (! $coordinates) {
                        $this->warn("  ✗ Nessuna geometria disponibile, saltato");
                        Log::warning('Pole has no geometry, skipped', ['pole_id' => $pole->id]);
                        $skippedNoGeometry++;
                        $processedCount++;
                        continue;
                    }

                    $this->line("  → Chiamata Nominatim per lat={$coordinates->lat}, lon={$coordinates->lon}");
                    Log::info('Calling Nominatim for pole', [
                        'pole_id' => $pole->id,
                        'lat' => $coordinates->lat,
                        'lon' => $coordinates->lon,
                    ]);

                    try {
                        $nominatimResult = $nominatimClient->reverseGeocode(
                            (float) $coordinates->lat,
                            (float) $coordinates->lon
                        );

                        $name = $this->extractPlaceNameFromNominatim($nominatimResult);
                        $source = 'Nominatim';

                        if (! empty($name)) {
                            $this->info("  ✓ Recuperato da Nominatim: '{$name}'");
                            Log::info('Pole name retrieved from Nominatim', [
                                'pole_id' => $pole->id,
                                'name' => $name,
                                'dry_run' => $dryRun,
                            ]);
                            if (! $dryRun) {
                                $pole->updateQuietly(['name' => $name]);
                            } else {
                                $this->line("  [DRY-RUN] Verrebbe aggiornato: name = '{$name}'");
                            }
                            $updatedFromNominatim++;
                        } else {
                            $this->warn("  ✗ Nominatim non ha restituito un name valido");
                            Log::warning('Nominatim did not return a valid name', [
                                'pole_id' => $pole->id,
                                'nominatim_result' => $nominatimResult,
                            ]);
                        }
                    } catch (Exception $e) {
                        $this->error("  ✗ Errore Nominatim: {$e->getMessage()}");
                        Log::warning('Nominatim reverse geocoding failed', [
                            'pole_id' => $pole->id,
                            'lat' => $coordinates->lat,
                            'lon' => $coordinates->lon,
                            'error' => $e->getMessage(),
                        ]);
                        // Continua senza aggiornare
                    }
                }

                $processedCount++;
            } catch (\Throwable $e) {
                $errorCount++;
                $this->error("Pole #{$pole->id}: Errore: {$e->getMessage()}");
                Log::error('Error processing pole', [
                    'pole_id' => $pole->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $processedCount++;
            }
            $this->newLine();
        }

        $this->newLine(2);
        $this->info('Processo completato!');
        $this->table(
            ['Metrica', 'Conteggio'],
            [
                ['Totali processati', $processedCount],
                ['Aggiornati da OsmTags', $updatedFromOsmTags],
                ['Aggiornati da Nominatim', $updatedFromNominatim],
                ['Saltati (no geometry)', $skippedNoGeometry],
                ['Errori', $errorCount],
            ]
        );

        if ($dryRun) {
            $this->warn('DRY RUN: nessuna modifica è stata salvata nel database');
        }

        return Command::SUCCESS;
    }

    /**
     * Estrae le coordinate dal palo
     */
    private function getCoordinatesFromPole(Poles $pole): ?object
    {
        $coordinates = DB::table('poles')
            ->where('id', $pole->id)
            ->selectRaw('ST_X(geometry::geometry) as lon, ST_Y(geometry::geometry) as lat')
            ->first();

        if (! $coordinates || ! $coordinates->lat || ! $coordinates->lon) {
            return null;
        }

        return $coordinates;
    }

    /**
     * Estrae il nome località più appropriato dalla risposta di Nominatim
     */
    private function extractPlaceNameFromNominatim(array $nominatimData): string
    {
        // Priorità: name > address.hamlet > address.village > address.suburb > address.town > address.city
        if (! empty($nominatimData['name'])) {
            return $nominatimData['name'];
        }

        $address = $nominatimData['address'] ?? [];

        // Ordine di priorità per località
        $priorities = ['hamlet', 'village', 'suburb', 'town', 'city', 'municipality'];

        foreach ($priorities as $key) {
            if (! empty($address[$key])) {
                return $address[$key];
            }
        }

        // Fallback al display_name troncato
        if (! empty($nominatimData['display_name'])) {
            $parts = explode(',', $nominatimData['display_name']);

            return trim($parts[0]);
        }

        return '';
    }
}
