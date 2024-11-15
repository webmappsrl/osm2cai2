<?php

namespace App\Jobs;

use App\Services\IntersectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Wm\WmPackage\Jobs\Abstracts\BaseJob;

class RecalculateIntersections extends BaseJob
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected function getRedisLockKey(): string
    {
        return $this->region ? $this->region->id : $this->hikingRoute->id;
    }

    protected function getLogChannel(): string
    {
        return 'calculate-intersections';
    }

    protected $region;
    protected $hikingRoute;

    public function __construct($region = null, $hikingRoute = null)
    {
        $this->region = $region;
        $this->hikingRoute = $hikingRoute;
    }

    public function handle()
    {
        $intersectionService = new IntersectionService();
        $intersectionService->calculateIntersections($this->region, $this->hikingRoute);
    }
}
