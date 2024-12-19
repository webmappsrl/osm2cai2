<?php

namespace App\Jobs;

use App\Models\HikingRoute;
use App\Services\OsmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckHikingRouteExistenceOnOSM implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected HikingRoute $hr;

    protected OsmService $service;

    /**
     * Create a new job instance.
     */
    public function __construct(HikingRoute $hr)
    {
        $this->hr = $hr;
        $this->service = OsmService::getService();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->service->hikingRouteExists($this->hr->relation_id) === false) {
            $this->hr->deleted_on_osm = true;
            $this->hr->saveQuietly();
        }
    }
}
