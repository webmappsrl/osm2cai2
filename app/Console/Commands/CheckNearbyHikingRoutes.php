<?php

namespace App\Console\Commands;

use App\Jobs\CheckNearbyHikingRoutesJob;
use App\Models\CaiHut;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckNearbyHikingRoutes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:check-nearby-hiking-routes {cai_hut_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check nearby hiking routes for a cai hut';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $caiHutId = $this->argument('cai_hut_id');
        $buffer = config('osm2cai.hiking_route_buffer');

        $caiHut = CaiHut::find($caiHutId);

        if (! $caiHut) {
            Log::warning("Cai hut {$caiHutId} not found");

            return;
        }

        if (! $caiHut->geometry) {
            Log::warning("Cai hut {$caiHut->id} has no geometry");

            return;
        }

        CheckNearbyHikingRoutesJob::dispatch($caiHut, $buffer);
    }
}
