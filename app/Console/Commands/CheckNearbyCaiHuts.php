<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use Illuminate\Console\Command;
use App\Jobs\CheckNearbyHutsJob;
use Illuminate\Support\Facades\Log;

class CheckNearbyCaiHuts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai2:check-nearby-huts {hiking_route_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check nearby cai huts for a hiking route';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $buffer = config('osm2cai.hiking_route_buffer');

        $hikingRoute = HikingRoute::find($this->argument('hiking_route_id'));

        if (! $hikingRoute) {
            Log::warning("Hiking route {$this->argument('hiking_route_id')} not found");

            return;
        }

        if (! $hikingRoute->geometry) {
            Log::warning("Hiking route {$hikingRoute->id} has no geometry");

            return;
        }

        CheckNearbyHutsJob::dispatch($hikingRoute, $buffer);
    }
}
