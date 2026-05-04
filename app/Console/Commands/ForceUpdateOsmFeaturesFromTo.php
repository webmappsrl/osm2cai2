<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ForceUpdateOsmFeaturesFromTo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:force-update-osmfeatures-from-to
                            {--dry-run : Simula il comando senza salvare nulla}
                            {--batch-size=3 : Numero massimo di richieste HTTP concorrenti}
                            {--delay=500 : Pausa in millisecondi tra un batch e il successivo}
                            {--timeout=30 : Timeout in secondi per ogni richiesta HTTP}
                            {--limit=0 : Numero massimo di hiking routes da elaborare (0 = nessun limite)}
                            {--details : Stampa/logga il dettaglio per ogni record (valori locali vs API e motivazione)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Forza l'aggiornamento dei campi 'from' e 'to' nelle properties delle HikingRoute "
        ."recuperandoli da osmfeatures (anche per percorsi con osm2cai_status = 4). "
        .'Le richieste HTTP vengono eseguite in batch concorrenti.';

    private bool $isDryRun = false;

    private bool $isDetails = false;

    private int $processed = 0;

    private int $updated = 0;

    private int $skipped = 0;

    private int $errors = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->isDryRun = (bool) $this->option('dry-run');
        $this->isDetails = (bool) $this->option('details');
        $batchSize = max(1, (int) $this->option('batch-size'));
        $delayMs = max(0, (int) $this->option('delay'));
        $timeout = max(1, (int) $this->option('timeout'));
        $limit = max(0, (int) $this->option('limit'));

        $logger = Log::channel('wm-osmfeatures');
        $prefix = $this->isDryRun ? '[DRY RUN] ' : '';

        $this->info($prefix."Avvio aggiornamento forzato di 'from'/'to' su HikingRoute (batch={$batchSize}, delay={$delayMs}ms).");
        $logger->info("ForceUpdateOsmFeaturesFromTo: start (dry-run={$this->isDryRun}, details={$this->isDetails}, batch={$batchSize}, delay={$delayMs}ms, limit={$limit})");

        $ids = $this->buildBaseQuery()
            ->when($limit > 0, fn ($q) => $q->limit($limit))
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $total = count($ids);

        if ($total === 0) {
            $this->info('Nessuna HikingRoute da aggiornare: tutte le routes hanno già from e to nelle properties.');

            return Command::SUCCESS;
        }

        $this->info("Trovate {$total} HikingRoute con 'from' e/o 'to' mancanti nelle properties.");

        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        $batches = array_chunk($ids, $batchSize);
        $lastIndex = count($batches) - 1;

        foreach ($batches as $index => $batchIds) {
            $routes = HikingRoute::whereIn('id', $batchIds)
                ->get()
                ->keyBy('id');

            $responses = $this->fetchBatch($routes, $timeout);

            foreach ($routes as $id => $route) {
                $this->processed++;
                $this->processSingleRoute($route, $responses[(string) $id] ?? null, $logger);
                $progressBar->advance();
            }

            if ($delayMs > 0 && $index < $lastIndex) {
                usleep($delayMs * 1000);
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->displaySummary($logger);

        return Command::SUCCESS;
    }

    /**
     * Build the base query: only routes with osmfeatures_id and missing from/to in properties.
     * Note: the filter is applied at PHP level only when osm2cai_status = 4 is desired too,
     * which is the default behavior of this command (no status filter is applied here).
     */
    private function buildBaseQuery()
    {
        return HikingRoute::query()
            ->whereNotNull('osmfeatures_id')
            ->where(function ($q) {
                $q->whereNull('properties')
                    ->orWhereRaw("(properties->>'from') IS NULL")
                    ->orWhereRaw("(properties->>'from') = ''")
                    ->orWhereRaw("(properties->>'to') IS NULL")
                    ->orWhereRaw("(properties->>'to') = ''");
            });
    }

    /**
     * Fetch a batch of routes concurrently using Http::pool.
     * Returns an associative array: [route_id => Response|Throwable|null].
     */
    private function fetchBatch($routes, int $timeout): array
    {
        $items = $routes->values()->all();

        try {
            $responses = Http::pool(function (Pool $pool) use ($items, $timeout) {
                return array_map(function (HikingRoute $route) use ($pool, $timeout) {
                    $url = HikingRoute::getApiSingleFeature($route->osmfeatures_id);

                    return $pool->as((string) $route->id)
                        ->timeout($timeout)
                        ->get($url);
                }, $items);
            });
        } catch (Throwable $e) {
            Log::channel('wm-osmfeatures')->error('ForceUpdateOsmFeaturesFromTo: pool error - '.$e->getMessage());

            return [];
        }

        return $responses;
    }

    /**
     * Process the response for a single hiking route.
     */
    private function processSingleRoute(HikingRoute $route, mixed $response, $logger): void
    {
        if ($response instanceof Throwable || $response instanceof ConnectionException) {
            $this->errors++;
            $logger->error("ForceUpdateOsmFeaturesFromTo: id {$route->id} ({$route->osmfeatures_id}) - request failed: ".$response->getMessage());

            return;
        }

        if (! $response instanceof Response || $response->failed() || $response->json() === null) {
            $this->errors++;
            $status = $response instanceof Response ? $response->status() : 'no-response';
            $logger->error("ForceUpdateOsmFeaturesFromTo: id {$route->id} ({$route->osmfeatures_id}) - invalid response (status: {$status})");

            return;
        }

        $data = $response->json();
        $from = $data['properties']['from'] ?? null;
        $to = $data['properties']['to'] ?? null;

        $properties = is_array($route->properties) ? $route->properties : [];
        $localFrom = $properties['from'] ?? null;
        $localTo = $properties['to'] ?? null;
        $changed = false;
        $reasons = [];

        // Preparazione aggiornamento "ibrido": oltre a properties, aggiorna osmfeatures_data.properties.from/to
        // solo se sono vuoti e solo con valori non vuoti provenienti dall'API.
        $osmfeaturesData = $route->osmfeatures_data;
        if (is_string($osmfeaturesData)) {
            $decoded = json_decode($osmfeaturesData, true);
            if (is_array($decoded)) {
                $osmfeaturesData = $decoded;
            }
        }
        $osmfeaturesDataChanged = false;
        if (is_array($osmfeaturesData)) {
            $osmfeaturesData['properties'] ??= [];
            $osfLocalFrom = $osmfeaturesData['properties']['from'] ?? null;
            $osfLocalTo = $osmfeaturesData['properties']['to'] ?? null;
        } else {
            $osfLocalFrom = null;
            $osfLocalTo = null;
        }

        if (! $this->isEmptyValue($from) && $this->isEmptyValue($localFrom)) {
            $properties['from'] = $from;
            $changed = true;
            $reasons[] = "set from (local empty, api='{$from}')";
        }

        if (! $this->isEmptyValue($to) && $this->isEmptyValue($localTo)) {
            $properties['to'] = $to;
            $changed = true;
            $reasons[] = "set to (local empty, api='{$to}')";
        }

        if (is_array($osmfeaturesData)) {
            if (! $this->isEmptyValue($from) && $this->isEmptyValue($osfLocalFrom)) {
                $osmfeaturesData['properties']['from'] = $from;
                $osmfeaturesDataChanged = true;
                $reasons[] = "set osmfeatures_data.from (was empty, api='{$from}')";
            }
            if (! $this->isEmptyValue($to) && $this->isEmptyValue($osfLocalTo)) {
                $osmfeaturesData['properties']['to'] = $to;
                $osmfeaturesDataChanged = true;
                $reasons[] = "set osmfeatures_data.to (was empty, api='{$to}')";
            }
        }

        if ($this->isDetails) {
            $msg = "ForceUpdateOsmFeaturesFromTo: id {$route->id} ({$route->osmfeatures_id}) "
                ."local[from='".($localFrom ?? 'NULL')."', to='".($localTo ?? 'NULL')."'] "
                ."api[from='".($from ?? 'NULL')."', to='".($to ?? 'NULL')."']";
            $this->line($msg);
            $logger->info($msg);
        }

        if (! $changed && ! $osmfeaturesDataChanged) {
            $this->skipped++;
            $reason = $this->buildSkipReason($localFrom, $localTo, $from, $to);
            $logger->info("ForceUpdateOsmFeaturesFromTo: id {$route->id} ({$route->osmfeatures_id}) - nothing to update ({$reason})");
            if ($this->isDetails) {
                $this->line("  -> skip: {$reason}");
            }

            return;
        }

        if ($this->isDryRun) {
            $this->updated++;
            $actions = implode(', ', $reasons);
            $logger->info("[DRY RUN] ForceUpdateOsmFeaturesFromTo: id {$route->id} ({$route->osmfeatures_id}) - would update ({$actions})");
            if ($this->isDetails) {
                $this->line("  -> dry-run: {$actions}");
            }

            return;
        }

        try {
            // NOTA: usiamo update() (non quietly) così scattano gli observer
            // e quindi il job AWS della traccia viene dispatchato automaticamente.
            $updatePayload = ['properties' => $properties];
            if ($osmfeaturesDataChanged) {
                $updatePayload['osmfeatures_data'] = $osmfeaturesData;
            }
            $route->update($updatePayload);
            $this->updated++;
            $actions = implode(', ', $reasons);
            $logger->info("ForceUpdateOsmFeaturesFromTo: id {$route->id} ({$route->osmfeatures_id}) - updated ({$actions}) (osm2cai_status={$route->osm2cai_status})");
            if ($this->isDetails) {
                $this->line("  -> updated: {$actions}");
            }
        } catch (Throwable $e) {
            $this->errors++;
            $logger->error("ForceUpdateOsmFeaturesFromTo: id {$route->id} ({$route->osmfeatures_id}) - update failed: ".$e->getMessage());
        }
    }

    private function isEmptyValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value) && trim($value) === '') {
            return true;
        }

        return false;
    }

    private function buildSkipReason(mixed $localFrom, mixed $localTo, mixed $apiFrom, mixed $apiTo): string
    {
        $localFromEmpty = $this->isEmptyValue($localFrom);
        $localToEmpty = $this->isEmptyValue($localTo);
        $apiFromEmpty = $this->isEmptyValue($apiFrom);
        $apiToEmpty = $this->isEmptyValue($apiTo);

        if (! $localFromEmpty && ! $localToEmpty) {
            return 'local already has from/to';
        }

        if ($localFromEmpty && $localToEmpty && $apiFromEmpty && $apiToEmpty) {
            return 'api returned empty from/to';
        }

        $parts = [];
        if ($localFromEmpty) {
            $parts[] = $apiFromEmpty ? 'from missing in api' : 'from already handled';
        } else {
            $parts[] = 'from already set locally';
        }

        if ($localToEmpty) {
            $parts[] = $apiToEmpty ? 'to missing in api' : 'to already handled';
        } else {
            $parts[] = 'to already set locally';
        }

        return implode('; ', $parts);
    }

    private function displaySummary($logger): void
    {
        $prefix = $this->isDryRun ? '[DRY RUN] ' : '';

        $this->info($prefix.'Elaborazione completata.');
        $this->table(
            ['Metrica', 'Conteggio'],
            [
                ['Totale processate', $this->processed],
                [$this->isDryRun ? 'Aggiornabili (simulate)' : 'Aggiornate', $this->updated],
                ['Saltate (già valorizzate o API senza dati)', $this->skipped],
                ['Errori', $this->errors],
            ]
        );

        $logger->info("ForceUpdateOsmFeaturesFromTo: end - processed={$this->processed}, updated={$this->updated}, skipped={$this->skipped}, errors={$this->errors}, dry-run={$this->isDryRun}, details={$this->isDetails}");
    }
}
