<?php

namespace App\Jobs;

use App\Services\IntersectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalculateIntersections implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $region;
    protected $hikingRoute;

    public function __construct($region = null, $hikingRoute = null)
    {
        $this->region = $region;
        $this->hikingRoute = $hikingRoute;
    }

    public function handle(IntersectionService $intersectionService)
    {
        $intersectionService->calculateIntersections($this->region, $this->hikingRoute);
    }
}
