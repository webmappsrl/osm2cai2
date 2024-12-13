<?php

namespace App\Console\Commands;

use App\Models\HikingRoute;
use Illuminate\Console\Command;

class CheckHikingRoutesGeometry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:check_hiking_routes_geometry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Iterates over all hiking routes to populate geometry_check attribute checking the geometry api';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        ini_set('memory_limit', '-1');
        HikingRoute::all('id', 'is_geometry_correct')->each(function ($hr) {
            $this->info("Checking hiking route (id: {$hr->id})");
            $newGeometryCheck = $hr->hasCorrectGeometry();

            if ($hr->is_geometry_correct !== $newGeometryCheck) {
                $hr->is_geometry_correct = $newGeometryCheck;
                $hr->save();
                $this->info("Updated is_geometry_correct for {$hr->name} (id: {$hr->id})");
            } else {
                $this->info("No change needed for {$hr->name} (id: {$hr->id})");
            }
        });

        return 0;
    }
}
