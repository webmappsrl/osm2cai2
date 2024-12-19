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

    public function handle(): void
    {
        Log::info("Checking nearby {$this->getTargetTableName()} for {$this->getSourceTableName()} {$this->sourceModel->id}");

        try {
            if (! $this->sourceModel->geometry) {
                Log::warning("{$this->getSourceTableName()} {$this->sourceModel->id} has no geometry");

                return;
            }

            if ($this->buffer < 0) {
                Log::warning('Buffer distance must be positive');

                return;
            }

            $nearbyEntities = DB::select("
                SELECT {$this->getTargetTableName()}.id 
                FROM {$this->getTargetTableName()}, {$this->getSourceTableName()}
                WHERE {$this->getSourceTableName()}.id = :sourceId 
                AND ST_DWithin(
                    {$this->getSourceTableName()}.geometry::geography,
                    {$this->getTargetTableName()}.geometry::geography, 
                    :buffer
                )
            ", [
                'sourceId' => $this->sourceModel->id,
                'buffer' => $this->buffer,
            ]); //we cast to geography to use ST_DWithin because is more accurate

            if (empty($nearbyEntities)) {
                Log::info("No nearby {$this->getTargetTableName()} found for {$this->getSourceTableName()} {$this->sourceModel->id}");

                return;
            }

            $nearbyIds = array_map(fn ($entity) => $entity->id, $nearbyEntities);

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
