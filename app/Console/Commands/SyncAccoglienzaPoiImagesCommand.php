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
                            {--source-key= : Processa solo il POI con questo source_key (es. pt_accoglienza_unofficial:1894)}';

    protected $description = 'Importa le immagini mancanti dei punti accoglienza da pt_accoglienza_unofficial (idempotente: salta le già presenti).';

    private string $baseUrl = 'https://sentieroitaliamappe.cai.it/index.php/view/media/getMedia?repository=sicaipubblico&project=SICAI_Pubblico&path=';

    /** @var string[] */
    private array $photoColumns = ['foto02', 'foto03', 'foto04', 'foto05'];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $filterSourceKey = $this->option('source-key');

        if ($dryRun) {
            $this->warn('Modalità dry-run: nessuna modifica al database.');
        }

        if (! config('database.connections.sicai_postgis.host')) {
            $this->error('Connessione Sicai PostGIS non configurata: imposta SICAI_POSTGIS_DB_* nel .env');

            return self::FAILURE;
        }

        $this->info('Lettura pt_accoglienza_unofficial dal DB Sicai...');

        try {
            $rows = $this->fetchRows();
        } catch (\Throwable $e) {
            $this->error('Errore lettura DB Sicai: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('Trovate ' . count($rows) . ' righe.');

        $stats = $this->syncImages($rows, $dryRun, $filterSourceKey);

        $this->newLine();
        $this->info('=== Riepilogo ===');
        $this->table(
            ['Esito', 'Numero'],
            [
                ['Righe elaborate', $stats['rows_total']],
                ['Righe senza EC POI corrispondente', $stats['rows_without_poi']],
                ['Righe con immagini', $stats['rows_with_images']],
                ['Righe senza immagini', $stats['rows_without_images']],
                ['Immagini totali trovate', $stats['images_total']],
                ['Immagini importate', $stats['images_attached']],
                ['Immagini già presenti (saltate)', $stats['images_skipped_existing']],
                ['Immagini fallite', $stats['images_failed']],
            ]
        );

        if ($dryRun) {
            $this->warn('Nessuna modifica scritta: eseguire senza --dry-run per applicare.');
        }

        return self::SUCCESS;
    }

    private function fetchRows(): array
    {
        $conn = DB::connection('sicai_postgis');

        $geomColumn = $this->getGeometryColumnName($conn);
        $rawRows = $conn->select('SELECT * FROM "pt_accoglienza_unofficial"');

        $items = [];
        foreach ($rawRows as $index => $row) {
            $raw = (array) $row;

            $externalId = $raw['id'] ?? $raw['gid'] ?? null;
            $sourceKey = $externalId !== null
                ? 'pt_accoglienza_unofficial:' . $externalId
                : 'pt_accoglienza_unofficial:row_' . ($index + 1);

            $items[] = [
                'source_key' => $sourceKey,
                'raw' => $raw,
                'geometry_column' => $geomColumn,
            ];
        }

        return $items;
    }

    private function syncImages(array $rows, bool $dryRun, ?string $filterSourceKey): array
    {
        $rowsTotal = 0;
        $rowsWithoutPoi = 0;
        $rowsWithImages = 0;
        $rowsWithoutImages = 0;
        $imagesTotal = 0;
        $imagesAttached = 0;
        $imagesSkippedExisting = 0;
        $imagesFailed = 0;

        foreach ($rows as $row) {
            $sourceKey = $row['source_key'];
            $raw = $row['raw'];

            if ($filterSourceKey !== null && $sourceKey !== $filterSourceKey) {
                continue;
            }

            $rowsTotal++;

            /** @var EcPoi|null $ecPoi */
            $ecPoi = EcPoi::query()
                ->where('app_id', 2)
                ->where('properties->sicai->source_key', $sourceKey)
                ->first();

            if (! $ecPoi) {
                $rowsWithoutPoi++;
                $this->line(sprintf('Nessun EC POI per source_key %s, salto.', $sourceKey));

                continue;
            }

            $relativePaths = [];
            foreach ($this->photoColumns as $column) {
                $path = $raw[$column] ?? null;
                if (is_string($path)) {
                    $path = trim($path);
                    if ($path !== '') {
                        $relativePaths[] = $path;
                    }
                }
            }

            if (empty($relativePaths)) {
                $rowsWithoutImages++;

                continue;
            }

            $rowsWithImages++;

            foreach ($relativePaths as $relativePath) {
                $imagesTotal++;

                $fileName = basename($relativePath);
                $pathForUrl = str_replace(' ', '%20', $relativePath);
                $url = $this->baseUrl . $pathForUrl;

                $existingMedia = $ecPoi->media
                    ->where('collection_name', 'default')
                    ->firstWhere('file_name', $fileName);

                if ($existingMedia) {
                    $imagesSkippedExisting++;
                    $this->line(sprintf(
                        '[SKIP] EC POI %s (%d): %s già presente',
                        $sourceKey,
                        $ecPoi->id,
                        $fileName
                    ));

                    continue;
                }

                if ($dryRun) {
                    $this->comment(sprintf(
                        '[DRY-RUN] Importerei EC POI %s (%d): %s',
                        $sourceKey,
                        $ecPoi->id,
                        $relativePath
                    ));

                    continue;
                }

                try {
                    $ecPoi
                        ->addMediaFromUrl($url)
                        ->usingFileName($fileName)
                        ->usingName($fileName)
                        ->toMediaCollection('default');

                    $imagesAttached++;
                    $this->info(sprintf(
                        '[OK] EC POI %s (%d): %s',
                        $sourceKey,
                        $ecPoi->id,
                        $relativePath
                    ));

                    Log::channel('single')->info('sync-accoglienza-poi-images: importata', [
                        'source_key' => $sourceKey,
                        'ec_poi_id' => $ecPoi->id,
                        'file' => $relativePath,
                    ]);
                } catch (\Throwable $e) {
                    $imagesFailed++;
                    $this->error(sprintf(
                        '[FAIL] EC POI %s (%d): %s — %s',
                        $sourceKey,
                        $ecPoi->id,
                        $relativePath,
                        $e->getMessage()
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

        return [
            'rows_total' => $rowsTotal,
            'rows_without_poi' => $rowsWithoutPoi,
            'rows_with_images' => $rowsWithImages,
            'rows_without_images' => $rowsWithoutImages,
            'images_total' => $imagesTotal,
            'images_attached' => $imagesAttached,
            'images_skipped_existing' => $imagesSkippedExisting,
            'images_failed' => $imagesFailed,
        ];
    }

    private function getGeometryColumnName(\Illuminate\Database\Connection $conn): string
    {
        $col = $conn->selectOne(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = 'pt_accoglienza_unofficial'
             AND udt_name = 'geometry'
             LIMIT 1"
        );

        return ($col && isset($col->column_name)) ? $col->column_name : 'geom';
    }
}
