<?php

namespace App\Console\Commands;

use App\Models\UgcPoi;
use App\Models\UgcTrack;
use App\Services\UgcMediaHashService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;
use Wm\WmPackage\Models\Media;

/**
 * STRATO 2 di oc:8867 — resta anche dopo l'aggiornamento di wm-package.
 *
 * Popola custom_properties.sha256 dei media UGC che non ce l'hanno. La bonifica dei duplicati
 * confronta le fotografie per hash: se mancasse, lo calcolerebbe scaricando il file e lo salverebbe,
 * cioè scriverebbe sul database anche in dry-run. Questo passaggio va quindi eseguito prima del
 * dry-run. Senza --execute si limita a contare i media privi di hash, senza chiamare
 * UgcMediaHashService::hashOf() (che scrive).
 */
class BackfillUgcMediaHashCommand extends Command
{
    protected $signature = 'osm2cai:backfill-ugc-media-hash
                            {--execute : Calcola e salva gli hash (senza, conta soltanto)}
                            {--chunk=500 : Dimensione dei blocchi di media}';

    protected $description = 'Popola lo sha256 nei custom_properties dei media di UgcPoi e UgcTrack che non ce l\'hanno';

    public function handle(UgcMediaHashService $hashes): int
    {
        $execute = (bool) $this->option('execute');
        $chunk = max(1, (int) $this->option('chunk'));

        $query = Media::query()
            // Il model_type dei media è il morph class (morphMap), non il nome della classe: si legge dai modelli.
            ->whereIn('model_type', [(new UgcPoi)->getMorphClass(), (new UgcTrack)->getMorphClass()])
            ->whereNull('custom_properties->'.UgcMediaHashService::HASH_PROPERTY);

        $total = (clone $query)->count();
        $this->info("Media UGC senza sha256: {$total}");

        if (! $execute) {
            $this->warn('Modalità sola lettura: nessun hash calcolato. Usare --execute per popolarli.');

            return self::SUCCESS;
        }

        $ok = 0;
        $failed = 0;

        // chunkById e non chunk: i record cambiano mentre si itera (regola di oc:7954).
        $query->chunkById($chunk, function ($medias) use ($hashes, &$ok, &$failed) {
            foreach ($medias as $media) {
                try {
                    $hash = $hashes->hashOf($media);
                } catch (Throwable $e) {
                    $hash = null;
                    Log::channel('duplicated-ugc')->warning('backfill sha256: errore sul media', [
                        'media_id' => $media->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                if ($hash === null) {
                    $failed++;
                    Log::channel('duplicated-ugc')->warning('backfill sha256: file non leggibile', ['media_id' => $media->id]);
                } else {
                    $ok++;
                }
            }
        });

        $this->info("Hash calcolati: {$ok}. Falliti: {$failed}.");
        Log::channel('duplicated-ugc')->info('backfill sha256 terminato', ['ok' => $ok, 'failed' => $failed]);

        return self::SUCCESS;
    }
}
