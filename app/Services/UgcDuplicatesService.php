<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Wm\WmPackage\Models\Abstracts\GeometryModel;
use Wm\WmPackage\Models\Media;
use Wm\WmPackage\Services\BaseService;

/**
 * STRATO 1 — DA CANCELLARE (oc:8867): copia locale destinata a essere cancellata.
 *
 * Porting di Wm\WmPackage\Services\Models\UgcDuplicatesService di wm-package (oc:8718).
 * Va eliminata quando wm-package verrà aggiornato con la correzione.
 *
 * Normalizzazione degli UGC (tracce e POI) duplicati per properties.uuid (oc:8718): nascono dai
 * retry dell'app quando la store non ha risposto. Il padre è la riga più vecchia; le copie vengono
 * unite nel padre, archiviate in ugc_duplicates_archive e cancellate. Ogni metodo riceve la classe
 * del modello (UgcTrack o UgcPoi): tabella e morph si ricavano da lì.
 */
class UgcDuplicatesService extends BaseService
{
    public const DEFAULT_MAX_DISTANCE_METERS = 1.0;

    /** Chiavi di properties scritte dal server: restano sempre quelle del padre. */
    public const SERVER_PROPERTY_KEYS = ['id', 'created_at', 'updated_at', 'taxonomy_where', 'taxonomyWheres'];

    /** @param class-string<GeometryModel> $modelClass */
    public function groups(string $modelClass, ?int $appId = null): Collection
    {
        $table = (new $modelClass)->getTable();
        $appFilter = $appId !== null ? 'AND app_id = ?' : '';
        $bindings = $appId !== null ? [$appId] : [];

        $rows = DB::select("
            SELECT properties->>'uuid' AS uuid,
                   array_agg(id ORDER BY created_at, id) AS ids
            FROM {$table}
            WHERE properties->>'uuid' IS NOT NULL AND properties->>'uuid' <> '' {$appFilter}
            GROUP BY properties->>'uuid'
            HAVING count(*) > 1
            ORDER BY min(id)
        ", $bindings);

        return collect($rows)->map(function ($row) use ($table) {
            $ids = array_map('intval', explode(',', trim($row->ids, '{}')));
            $parentId = array_shift($ids);

            return [
                'uuid' => $row->uuid,
                'parent_id' => $parentId,
                'copy_ids' => $ids,
                'max_distance_m' => $this->maxDistanceMeters($table, $parentId, $ids),
            ];
        });
    }

    /**
     * Distanza massima (metri) fra la geometria del padre e quella delle copie. La colonna è
     * geography: ST_HausdorffDistance non la accetta e su geometry 4326 misurerebbe in gradi.
     * Si proietta in 3857 e si corregge il fattore di scala con il coseno della latitudine.
     */
    public function maxDistanceMeters(string $table, int $parentId, array $copyIds): float
    {
        if ($copyIds === []) {
            return 0.0;
        }
        $placeholders = implode(',', array_fill(0, count($copyIds), '?'));
        $row = DB::selectOne("
            SELECT COALESCE(max(
                ST_HausdorffDistance(
                    ST_Transform(ST_Force2D(p.geometry::geometry), 3857),
                    ST_Transform(ST_Force2D(c.geometry::geometry), 3857)
                ) * cos(radians(ST_Y(ST_Centroid(p.geometry::geometry))))
            ), 0) AS d
            FROM {$table} p, {$table} c
            WHERE p.id = ? AND c.id IN ({$placeholders})
        ", array_merge([$parentId], $copyIds));

        return (float) $row->d;
    }

    /**
     * Unisce le copie nel padre. Restituisce i media scartati come doppioni: vanno cancellati dal
     * chiamante dopo il commit, perché Spatie cancella i file subito e il rollback non li riporta.
     *
     * @return Media[]
     */
    /** @param class-string<GeometryModel> $modelClass */
    public function mergeGroup(string $modelClass, array $group): array
    {
        $parentId = $group['parent_id'];
        $copyIds = $group['copy_ids'];
        $model = new $modelClass;
        $table = $model->getTable();
        $morph = $model->getMorphClass();

        return DB::transaction(function () use ($parentId, $copyIds, $morph, $table) {
            $rows = DB::select("SELECT id, properties::text AS properties, updated_at FROM {$table} WHERE id = ANY(?::bigint[]) ORDER BY id",
                ['{'.implode(',', array_merge([$parentId], $copyIds)).'}']);
            $byId = collect($rows)->keyBy('id')->map(fn ($r) => ['id' => (int) $r->id, 'properties' => json_decode($r->properties, true) ?? [], 'updated_at' => $r->updated_at]);
            $parentProps = $byId[$parentId]['properties'];

            $ordered = $byId->values()->sortBy([
                fn ($a, $b) => strcmp((string) ($a['properties']['updatedAt'] ?? ''), (string) ($b['properties']['updatedAt'] ?? '')),
                fn ($a, $b) => strcmp((string) $a['updated_at'], (string) $b['updated_at']),
                fn ($a, $b) => $a['id'] <=> $b['id'],
            ])->pluck('properties')->all();
            $merged = $this->mergedProperties($ordered, $parentProps);

            DB::update("UPDATE {$table} SET properties = ?::jsonb, name = COALESCE(?, name), updated_at = now() WHERE id = ?",
                [json_encode($merged), $merged['name'] ?? null, $parentId]);

            [$movedByCopy, $discarded] = $this->moveMedia($parentId, $copyIds, $morph);

            foreach ($copyIds as $copyId) {
                DB::insert("
                    INSERT INTO ugc_duplicates_archive (model_type, original_id, reference_id, uuid, user_id, app_id, properties, geometry, media_ids, original_created_at, original_updated_at, archived_at, created_at, updated_at)
                    SELECT ?, id, ?, properties->>'uuid', user_id, app_id, properties::jsonb, geometry, ?::jsonb, created_at, updated_at, now(), now(), now()
                    FROM {$table} WHERE id = ?
                ", [$morph, $parentId, json_encode($movedByCopy[$copyId] ?? []), $copyId]);
            }

            DB::delete("DELETE FROM {$table} WHERE id = ANY(?::bigint[])", ['{'.implode(',', $copyIds).'}']);

            return $discarded;
        });
    }

    /** Righe in ordine cronologico: ognuna va sopra la precedente; le chiavi del server restano del padre. */
    public function mergedProperties(array $rowsInOrder, array $parentProperties): array
    {
        $merged = array_merge(...array_map(fn ($p) => (array) $p, $rowsInOrder));
        foreach (self::SERVER_PROPERTY_KEYS as $key) {
            if (array_key_exists($key, $parentProperties)) {
                $merged[$key] = $parentProperties[$key];
            } else {
                unset($merged[$key]);
            }
        }

        return $merged;
    }

    /** @return array{0: array<int,int[]>, 1: Media[]} media spostati per copia, media scartati */
    public function moveMedia(int $parentId, array $copyIds, string $morph): array
    {
        $hashes = UgcMediaHashService::make();
        $known = Media::where('model_type', $morph)->where('model_id', $parentId)->get()
            ->map(fn (Media $m) => $hashes->hashOf($m))->filter()
            ->mapWithKeys(fn (string $hash) => [$hash => true])->all();

        $moved = [];
        $discarded = [];
        foreach ($copyIds as $copyId) {
            foreach (Media::where('model_type', $morph)->where('model_id', $copyId)->orderBy('id')->get() as $media) {
                $hash = $hashes->hashOf($media);
                // Hash non calcolabile (file illeggibile): il media si sposta comunque, non si scarta.
                if ($hash !== null && isset($known[$hash])) {
                    $discarded[] = $media;

                    continue;
                }
                if ($hash !== null) {
                    $known[$hash] = true;
                }
                DB::update('UPDATE media SET model_id = ? WHERE id = ?', [$parentId, $media->id]);
                $moved[$copyId][] = $media->id;
            }
        }

        return [$moved, $discarded];
    }
}
