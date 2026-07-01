<?php

namespace Tests\Unit\Observers;

use App\Models\HikingRoute;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Wm\WmPackage\Jobs\Track\UpdateEcTrackAwsJob;

class HikingRouteObserverTest extends TestCase
{
    /** @test */
    public function it_dispatches_update_ec_track_aws_job_for_child_when_parent_status_changes()
    {
        Queue::fake();
        Bus::fake();

        $parent = HikingRoute::factory()->create([
            'osm2cai_status' => 3,
            'validator_id' => null,
            'validation_date' => null,
            'geometry' => DB::raw("ST_GeomFromText('LINESTRINGZ(12.4924 41.8902 0, 12.4925 41.8903 0)', 4326)"),
        ]);

        $child = HikingRoute::factory()->create([
            'parent_hiking_route_id' => $parent->id,
            'osm2cai_status' => 3,
            'validator_id' => null,
            'validation_date' => null,
            'geometry' => DB::raw("ST_GeomFromText('LINESTRINGZ(12.4924 41.8902 0, 12.4925 41.8903 0)', 4326)"),
        ]);

        $parent->osm2cai_status = 4;
        $parent->save();

        Bus::assertDispatched(UpdateEcTrackAwsJob::class, function ($job) use ($child) {
            return $job->getEcTrack()->id === $child->id;
        });
    }

    /** @test */
    public function it_does_not_dispatch_pbf_batch_for_child_when_only_status_changes()
    {
        Queue::fake();
        Bus::fake();

        $parent = HikingRoute::factory()->create([
            'osm2cai_status' => 3,
            'validator_id' => null,
            'validation_date' => null,
            'geometry' => DB::raw("ST_GeomFromText('LINESTRINGZ(12.4924 41.8902 0, 12.4925 41.8903 0)', 4326)"),
        ]);

        $child = HikingRoute::factory()->create([
            'parent_hiking_route_id' => $parent->id,
            'osm2cai_status' => 3,
            'validator_id' => null,
            'validation_date' => null,
            'geometry' => DB::raw("ST_GeomFromText('LINESTRINGZ(12.4924 41.8902 0, 12.4925 41.8903 0)', 4326)"),
        ]);

        $parent->osm2cai_status = 4;
        $parent->save();

        $childBatches = Bus::batched(function ($batch) use ($child) {
            return str_contains($batch->name, "Track {$child->id}:");
        });

        $this->assertCount(0, $childBatches, 'Non deve essere generato un batch PBF per il figlio quando cambia solo lo status.');
    }
}
