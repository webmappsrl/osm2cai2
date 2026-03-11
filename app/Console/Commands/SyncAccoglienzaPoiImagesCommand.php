<?php

namespace App\Console\Commands;

use App\Models\EcPoi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncAccoglienzaPoiImagesCommand extends Command
{
    protected $signature = 'osm2cai:sync-accoglienza-poi-images
                            {--dry-run : Esegue senza scrivere sul database}
                            {--ec-poi-id= : Processa solo l\'EC POI con questo id}';

    protected $description = 'Importa le immagini mancanti dei punti accoglienza (max 4 per POI). Idempotente: salta le già presenti, rimuove i doppioni.';

    private string $baseUrl = 'https://sentieroitaliamappe.cai.it/index.php/view/media/getMedia?repository=sicaipubblico&project=SICAI_Pubblico&path=';

    /** @var string[] */
    private array $photoColumns = ['foto02', 'foto03', 'foto04', 'foto05'];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $filterEcPoiId = $this->option('ec-poi-id') !== null ? (int) $this->option('ec-poi-id') : null;

        if ($dryRun) {
            $this->warn('Modalità dry-run: nessuna modifica al database.');
        }

        $query = EcPoi::query()
            ->where('app_id', 2)
            ->whereNotNull('properties->sicai->source_key');

        if ($filterEcPoiId !== null) {
            $query->where('id', $filterEcPoiId);
        }

        $total = $query->count();
        $this->info("EC POI da processare: {$total}");

        $poisWithImages = 0;
        $poisWithoutImages = 0;
        $imagesTotal = 0;
        $imagesAttached = 0;
        $imagesSkipped = 0;
        $imagesFailed = 0;
        $duplicatesRemoved = 0;

        $query->chunkById(100, function ($pois) use (
            $dryRun,
            &$poisWithImages, &$poisWithoutImages,
            &$imagesTotal, &$imagesAttached, &$imagesSkipped, &$imagesFailed, &$duplicatesRemoved
        ) {
            foreach ($pois as $ecPoi) {
                $sicai = $ecPoi->properties['sicai'] ?? [];
                $sourceKey = $sicai['source_key'] ?? "ec_poi:{$ecPoi->id}";

                // Raccogli i path delle foto da properties->sicai (max 4)
                $relativePaths = [];
                foreach ($this->photoColumns as $column) {
                    $path = $sicai[$column] ?? null;
                    if (is_string($path)) {
                        $path = trim($path);
                        if ($path !== '') {
                            $relativePaths[] = $path;
                        }
                    }
                }

                if (empty($relativePaths)) {
                    $poisWithoutImages++;

                    continue;
                }

                $poisWithImages++;

                // Leggi i media esistenti direttamente dalla tabella (no cache Eloquent)
                $existingMediaRows = DB::table('media')
                    ->where('model_type', EcPoi::class)
                    ->where('model_id', $ecPoi->id)
                    ->where('collection_name', 'default')
                    ->orderBy('id')
                    ->get(['id', 'file_name']);

                // Rimuovi doppioni per file_name (case-insensitive), tieni il record con id più basso
                $seenFileNames = [];
                foreach ($existingMediaRows as $mediaRow) {
                    $key = strtolower($mediaRow->file_name);
                    if (isset($seenFileNames[$key])) {
                        if ($dryRun) {
                            $this->comment(sprintf(
                                '[DRY-RUN DUPLICATO] %s (%d): rimuoverei media id=%d (%s)',
                                $sourceKey, $ecPoi->id, $mediaRow->id, $mediaRow->file_name
                            ));
                        } else {
                            DB::table('media')->where('id', $mediaRow->id)->delete();
                            $this->warn(sprintf(
                                '[DUPLICATO RIMOSSO] %s (%d): media id=%d (%s)',
                                $sourceKey, $ecPoi->id, $mediaRow->id, $mediaRow->file_name
                            ));
                        }
                        $duplicatesRemoved++;
                    } else {
                        $seenFileNames[$key] = $mediaRow->id;
                    }
                }

                // Lista filename già presenti dopo la pulizia (normalizzati come fa Spatie)
                $existingFileNamesNorm = array_map(
                    fn ($f) => $this->spatieSanitize($f),
                    array_keys($seenFileNames)
                );
                $expectedCount = count($relativePaths);
                $presentCount = count($existingFileNamesNorm);

                // Se il numero di foto presenti è uguale a quelle attese, il POI è completo
                if ($presentCount === $expectedCount) {
                    $imagesSkipped += $expectedCount;
                    $imagesTotal += $expectedCount;
                    $this->line(sprintf('[COMPLETO] %s (%d): %d/%d foto già presenti', $sourceKey, $ecPoi->id, $presentCount, $expectedCount));

                    continue;
                }

                // Sanity check: più foto di quelle attese (anomalia)
                if ($presentCount > $expectedCount) {
                    $this->warn(sprintf(
                        '[ANOMALIA] %s (%d): %d foto in media ma ne sono attese solo %d — verifica manuale',
                        $sourceKey, $ecPoi->id, $presentCount, $expectedCount
                    ));
                }

                foreach ($relativePaths as $relativePath) {
                    $imagesTotal++;

                    $fileName = basename($relativePath);
                    $fileNameNorm = $this->spatieSanitize($fileName);

                    if (in_array($fileNameNorm, $existingFileNamesNorm, true)) {
                        $imagesSkipped++;
                        $this->line(sprintf('[SKIP] %s (%d): %s', $sourceKey, $ecPoi->id, $fileName));

                        continue;
                    }

                    if ($dryRun) {
                        $this->comment(sprintf('[DRY-RUN] %s (%d): %s', $sourceKey, $ecPoi->id, $relativePath));

                        continue;
                    }

                    $pathForUrl = str_replace(' ', '%20', $relativePath);
                    $url = $this->baseUrl . $pathForUrl;

                    try {
                        $ecPoi
                            ->addMediaFromUrl($url)
                            ->usingFileName($fileName)
                            ->usingName($fileName)
                            ->toMediaCollection('default');

                        $imagesAttached++;

                        // Aggiorna la lista locale per evitare doppioni nel loop corrente
                        $existingFileNamesNorm[] = $fileNameNorm;

                        $this->info(sprintf('[OK] %s (%d): %s', $sourceKey, $ecPoi->id, $relativePath));

                        Log::channel('single')->info('sync-accoglienza-poi-images: importata', [
                            'source_key' => $sourceKey,
                            'ec_poi_id' => $ecPoi->id,
                            'file' => $relativePath,
                        ]);
                    } catch (\Throwable $e) {
                        $imagesFailed++;
                        $this->error(sprintf(
                            '[FAIL] %s (%d): %s — %s',
                            $sourceKey, $ecPoi->id, $relativePath, $e->getMessage()
                        ));

                        Log::warning('sync-accoglienza-poi-images: import fallito', [
                            'source_key' => $sourceKey,
                            'ec_poi_id' => $ecPoi->id,
                            'url' => $url,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        });

        $this->newLine();
        $this->info('=== Riepilogo ===');
        $this->table(
            ['Esito', 'Numero'],
            [
                ['POI con immagini', $poisWithImages],
                ['POI senza immagini', $poisWithoutImages],
                ['Immagini totali attese', $imagesTotal],
                ['Immagini importate', $imagesAttached],
                ['Immagini già presenti (saltate)', $imagesSkipped],
                ['Doppioni rimossi', $duplicatesRemoved],
                ['Immagini fallite', $imagesFailed],
            ]
        );

        if ($dryRun) {
            $this->warn('Nessuna modifica scritta: eseguire senza --dry-run per applicare.');
        }

        if ($imagesFailed > 0) {
            $this->warn("Ci sono {$imagesFailed} immagini fallite: rilancia il comando per ritentarle.");
        }

        return self::SUCCESS;
    }

    /**
     * Applica la stessa sanitizzazione che usa Spatie Media Library di default.
     * Fonte: vendor/spatie/laravel-medialibrary/src/MediaCollections/FileAdder.php::defaultSanitizer
     */
    private function spatieSanitize(string $fileName): string
    {
        $sanitized = preg_replace('#\p{C}+#u', '', $fileName);
        $sanitized = str_replace(['#', '/', '\\', ' '], '-', $sanitized);

        return strtolower($sanitized);
    }
}
