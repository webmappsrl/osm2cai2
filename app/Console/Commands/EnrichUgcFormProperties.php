<?php

namespace App\Console\Commands;

use App\Models\UgcPoi;
use App\Models\UgcTrack;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Wm\WmPackage\Models\App;

class EnrichUgcFormProperties extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:enrich-ugc-form-properties
                           {--chunk=100 : Number of records to process at once}
                           {--dry-run : Show what would be updated without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aggiunge tutte le chiavi presenti nel form corrispondente dichiarato dentro POI/Track acquisition forms nella app corrispondente all\'oggetto properties.form degli UGC POI e Track che hanno raw_data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $chunkSize = (int) $this->option('chunk');

        if ($isDryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
        }

        // Processa UGC POI
        $totalPois = UgcPoi::whereNotNull('raw_data')->count();
        $totalTracks = UgcTrack::whereNotNull('raw_data')->count();
        $total = $totalPois + $totalTracks;

        if ($total === 0) {
            $this->warn('Nessun UGC POI o Track con raw_data trovato.');

            return 0;
        }

        $this->info("Trovati {$totalPois} UGC POI e {$totalTracks} UGC Track con raw_data da processare.");

        $processedCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        // Processa POI
        UgcPoi::whereNotNull('raw_data')
            ->orderBy('id')
            ->chunk($chunkSize, function ($pois) use (&$processedCount, &$updatedCount, &$skippedCount, &$errorCount, $isDryRun, $bar) {
                foreach ($pois as $poi) {
                    try {
                        $result = $this->processUgc($poi, 'poi', $isDryRun);

                        if ($result === 'updated') {
                            $updatedCount++;
                        } elseif ($result === 'skipped') {
                            $skippedCount++;
                        } elseif ($result === 'error') {
                            $errorCount++;
                        }

                        $processedCount++;
                        $bar->advance();
                    } catch (\Exception $e) {
                        $this->newLine();
                        $this->error("Errore processando POI ID {$poi->id}: ".$e->getMessage());
                        $errorCount++;
                        $processedCount++;
                        $bar->advance();
                    }
                }
            });

        // Processa Track
        UgcTrack::whereNotNull('raw_data')
            ->orderBy('id')
            ->chunk($chunkSize, function ($tracks) use (&$processedCount, &$updatedCount, &$skippedCount, &$errorCount, $isDryRun, $bar) {
                foreach ($tracks as $track) {
                    try {
                        $result = $this->processUgc($track, 'track', $isDryRun);

                        if ($result === 'updated') {
                            $updatedCount++;
                        } elseif ($result === 'skipped') {
                            $skippedCount++;
                        } elseif ($result === 'error') {
                            $errorCount++;
                        }

                        $processedCount++;
                        $bar->advance();
                    } catch (\Exception $e) {
                        $this->newLine();
                        $this->error("Errore processando Track ID {$track->id}: ".$e->getMessage());
                        $errorCount++;
                        $processedCount++;
                        $bar->advance();
                    }
                }
            });

        $bar->finish();
        $this->newLine(2);

        $this->info('📊 Riepilogo:');
        $this->info("   - Totali processati: {$processedCount}");
        $this->info("   - Aggiornati: {$updatedCount}");
        $this->info("   - Saltati: {$skippedCount}");
        $this->info("   - Errori: {$errorCount}");

        if ($isDryRun) {
            $this->info('   - Modalità: DRY RUN (nessuna modifica effettuata)');
        } else {
            $this->info('   - Modalità: LIVE (modifiche applicate)');
        }

        return 0;
    }

    /**
     * Processa un singolo UGC (POI o Track)
     *
     * @param  UgcPoi|UgcTrack  $ugc
     * @param  string  $type  'poi' o 'track'
     * @return string 'updated', 'skipped', o 'error'
     */
    private function processUgc($ugc, string $type, bool $isDryRun): string
    {
        $ugcType = $type === 'poi' ? 'POI' : 'Track';
        $ugcId = $ugc->id;

        // Recupera l'App
        if (! $ugc->app_id) {
            return 'skipped';
        }

        // Verifica che app_id sia un numero intero valido
        // Alcuni UGC potrebbero avere app_id come stringa (es. "iNaturalist")
        if (! is_numeric($ugc->app_id)) {
            Log::info("UGC {$ugcType} saltato: app_id non numerico", [
                "{$type}_id" => $ugcId,
                'reason' => 'app_id_non_numerico',
            ]);

            return 'skipped';
        }

        $app = App::find((int) $ugc->app_id);
        if (! $app) {
            return 'skipped';
        }

        // Recupera il form_id (da form_id o da properties['form']['id'])
        $formId = $ugc->form_id ?? null;
        if (! $formId && $ugc->properties) {
            $properties = is_string($ugc->properties) ? json_decode($ugc->properties, true) : $ugc->properties;
            if (is_array($properties) && isset($properties['form']['id'])) {
                $formId = $properties['form']['id'];
            }
        }

        if (! $formId) {
            return 'skipped';
        }

        // Recupera il form dall'App (POI o Track)
        if ($type === 'poi') {
            $form = $app->poiAcquisitionForm($formId);
        } else {
            $form = $app->trackAcquisitionForm($formId);
        }

        if (! $form || ! isset($form['fields']) || ! is_array($form['fields'])) {
            return 'skipped';
        }

        // Estrai tutte le chiavi dai fields del form
        $formFieldKeys = [];
        foreach ($form['fields'] as $field) {
            if (isset($field['name'])) {
                $formFieldKeys[] = $field['name'];
            }
        }

        if (empty($formFieldKeys)) {
            return 'skipped';
        }

        // Prepara le properties
        $properties = $ugc->properties;
        if (! $properties) {
            $properties = [];
        } elseif (is_string($properties)) {
            $properties = json_decode($properties, true);
            if (! is_array($properties)) {
                $properties = [];
            }
        }

        // Assicurati che esista la struttura form
        if (! isset($properties['form'])) {
            $properties['form'] = [];
        }

        // Aggiungi sempre l'id del form se non presente
        if (! isset($properties['form']['id'])) {
            $properties['form']['id'] = $formId;
        }

        // Aggiungi tutte le chiavi del form, anche se non valorizzate
        // E aggiungi anche i valori dal raw_data se esistono e non sono già in properties['form']
        $hasChanges = false;
        $rawData = $ugc->raw_data;
        if (is_string($rawData)) {
            $rawData = json_decode($rawData, true);
        }
        if (! is_array($rawData)) {
            $rawData = [];
        }

        // Traccia le chiavi aggiunte per il log
        $addedKeys = [];

        foreach ($formFieldKeys as $key) {
            // IMPORTANTE: Non sovrascrivere valori esistenti
            // Se la chiave esiste già in properties['form'] E ha un valore (non null, non vuoto), non la tocchiamo
            $existingValue = $properties['form'][$key] ?? null;
            $hasExistingValue = isset($properties['form'][$key]) && $existingValue !== null && $existingValue !== '';

            if (! $hasExistingValue) {
                // La chiave non esiste o ha valore null/vuoto, quindi possiamo aggiungerla/aggiornarla
                // IMPORTANTE: Aggiungi solo se c'è un valore valido (non null, non vuoto)
                if (isset($rawData[$key]) && $rawData[$key] !== null && $rawData[$key] !== '') {
                    $properties['form'][$key] = $rawData[$key];
                    $addedKeys[$key] = $rawData[$key];
                    $hasChanges = true;
                }
                // Se la chiave non esiste e non c'è valore nel raw_data, NON la aggiungiamo (non aggiungiamo null)
            }
            // Se la chiave esiste già con un valore, la saltiamo completamente (non sovrascriviamo)
        }

        if (! $hasChanges) {
            return 'skipped';
        }

        // Log solo delle chiavi aggiunte, non di tutto il form
        Log::info("UGC {$ugcType} aggiornato (ID: {$ugcId}): form arricchito", [
            'form_id' => $formId,
            'added_keys' => $addedKeys,
        ]);

        // Salva l'UGC
        if (! $isDryRun) {
            $ugc->properties = $properties;
            $ugc->saveQuietly();
        }

        return 'updated';
    }
}
