<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Wm\WmPackage\Models\App;

abstract class CheckNearbyEntitiesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Model $sourceModel;

    protected $buffer;

    public function __construct(Model $sourceModel, $buffer)
    {
        $this->sourceModel = $sourceModel;
        $this->buffer = $buffer;
    }

    protected function getSourceTableName(): string
    {
        return $this->sourceModel->getTable();
    }

    abstract protected function getTargetTableName(): string;

    abstract protected function getRelationshipMethod(): string;

    /**
     * Verifica se l'app corrente è l'app osm2cai
     * 
     * @return bool
     */
    protected function isOsm2caiApp(): bool
    {
        // Controlla se esiste una variabile d'ambiente per l'app corrente
        $currentAppSku = env('CURRENT_APP_SKU');

        if ($currentAppSku) {
            return $currentAppSku === 'it.webmapp.osm2cai';
        }

        // Fallback: controlla se esiste un'app con SKU it.webmapp.osm2cai
        try {
            $app = App::where('sku', 'it.webmapp.osm2cai')->first();
            return $app !== null;
        } catch (\Exception $e) {
            Log::warning('Error checking app 1: ' . $e->getMessage());
            return false;
        }
    }

    public function handle(): void
    {
        Log::info("Checking nearby {$this->getTargetTableName()} for {$this->getSourceTableName()} {$this->sourceModel->id}");

        try {
            // Controlla se l'app corrente è l'app osm2cai
            if (!$this->isOsm2caiApp()) {
                Log::info("Job skipped: not running on osm2cai app");
                return;
            }

            if (! $this->sourceModel->geometry) {
                Log::warning("{$this->getSourceTableName()} {$this->sourceModel->id} has no geometry");

                return;
            }

            if ($this->buffer < 0) {
                Log::warning('Buffer distance must be positive');

                return;
            }

            // Verifica che l'entità sorgente abbia un app_id valido
            if (!$this->sourceModel->app_id) {
                Log::warning("{$this->getSourceTableName()} {$this->sourceModel->id} has no app_id");

                return;
            }

            $nearbyEntities = DB::select("
                SELECT {$this->getTargetTableName()}.id 
                FROM {$this->getTargetTableName()}, {$this->getSourceTableName()}
                WHERE {$this->getSourceTableName()}.id = :sourceId 
                AND {$this->getTargetTableName()}.app_id = {$this->getSourceTableName()}.app_id
                AND ST_DWithin(
                    {$this->getSourceTableName()}.geometry::geography,
                    {$this->getTargetTableName()}.geometry::geography, 
                    :buffer
                )
            ", [
                'sourceId' => $this->sourceModel->id,
                'buffer' => $this->buffer,
            ]); // we cast to geography to use ST_DWithin because is more accurate

            if (empty($nearbyEntities)) {
                Log::info("No nearby {$this->getTargetTableName()} found for {$this->getSourceTableName()} {$this->sourceModel->id} (same app_id: {$this->sourceModel->app_id})");

                return;
            }

            $nearbyIds = array_map(fn($entity) => $entity->id, $nearbyEntities);

            $syncData = array_combine(
                $nearbyIds,
                array_fill(0, count($nearbyIds), [
                    'buffer' => $this->buffer,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );

            $relationshipMethod = $this->getRelationshipMethod();
            $this->sourceModel->$relationshipMethod()->syncWithoutDetaching($syncData);
        } catch (\Exception $e) {
            Log::error("Error checking nearby {$this->getTargetTableName()}", [
                'source_id' => $this->sourceModel->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
