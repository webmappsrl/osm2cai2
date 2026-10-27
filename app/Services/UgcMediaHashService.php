<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;
use Wm\WmPackage\Services\BaseService;

/**
 * STRATO 1 di oc:8867 — copia locale destinata a essere cancellata.
 *
 * Porting di Wm\WmPackage\Services\Models\UgcMediaHashService di wm-package (oc:8718).
 * Va eliminata quando wm-package verrà aggiornato con la correzione.
 *
 * Riconosce le immagini UGC per contenuto (oc:8718).
 *
 * L'app, a ogni retry di una store, rimanda tutte le immagini con gli stessi byte ma nomi
 * posizionali (image_0.jpg, …): il confronto si fa sullo sha256 del file. L'hash si salva nei
 * custom_properties del media all'upload; per i media che non ce l'hanno si calcola scaricando il
 * file, e poi si salva.
 */
class UgcMediaHashService extends BaseService
{
    public const HASH_PROPERTY = 'sha256';

    public function hashOfFile(string $path): string
    {
        return hash_file('sha256', $path);
    }

    /**
     * Hash del media, salvato nei custom_properties. Se il file non si legge (assente su S3, errore
     * temporaneo) restituisce null: il media conta come "diverso" e lo store o il command vanno
     * avanti, invece di fallire e far ritentare l'app all'infinito.
     */
    public function hashOf(Media $media): ?string
    {
        $hash = $media->getCustomProperty(self::HASH_PROPERTY);
        if (is_string($hash) && $hash !== '') {
            return $hash;
        }

        try {
            $stream = $media->stream();
            if (! is_resource($stream)) {
                throw new \RuntimeException('stream non disponibile');
            }
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);
            $hash = hash_final($context);
        } catch (Throwable $e) {
            Log::channel('ugc')->warning('Hash del media non calcolabile: '.$e->getMessage(), ['media_id' => $media->id]);

            return null;
        }

        $media->setCustomProperty(self::HASH_PROPERTY, $hash);
        $media->saveQuietly();

        return $hash;
    }

    public function findByContent(HasMedia $model, string $hash, string $collection = 'default'): ?Media
    {
        foreach ($model->getMedia($collection) as $media) {
            if ($this->hashOf($media) === $hash) {
                return $media;
            }
        }

        return null;
    }
}
