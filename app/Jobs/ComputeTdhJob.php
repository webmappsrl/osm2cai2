<?php

namespace App\Jobs;

use App\Models\HikingRoute;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ComputeTdhJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $hikingRouteId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($hikingRouteId)
    {
        $this->hikingRouteId = $hikingRouteId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $hikingRoute = HikingRoute::find($this->hikingRouteId);

        if ($hikingRoute) {
            $newTdh = $hikingRoute->computeTdh();
            if ($newTdh !== $hikingRoute->tdh) {
                $hikingRoute->tdh = $newTdh;
                $hikingRoute->saveQuietly();
            }
        }
    }
}
