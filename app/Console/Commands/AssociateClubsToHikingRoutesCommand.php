<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\HikingRoute;
use Illuminate\Console\Command;

class AssociateClubsToHikingRoutesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:associate-clubs-to-hiking-routes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize relationships between hiking routes and clubs.
This command retrieves all existing clubs and iterates through each one. For each club, it searches for the corresponding hiking route using the "source_ref" field. If a matching hiking route is found, the club is associated with the hiking route.

To ensure successful synchronization, verify that the "HikingRoute" and "Club" models have the appropriate "source_ref" and "cai_code" fields defined and that they are consistent with each other.

';

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
        $this->info('[START] Sync relationships between hiking routes and clubs');

        $clubs = Club::all();

        //HikingRoute has a source_ref field that is the same as the cai_code field in Section, so we can use that to match them
        foreach ($clubs as $club) {
            $this->info('Syncing club '.$club->name.' with hiking routes source ref: '.$club->cai_code);

            try {
                $hikingRoutes = HikingRoute::where('osmfeatures_data->properties->source_ref', 'like', '%'.$club->cai_code.'%')->get();
            } catch (\Exception $e) {
                $this->error('Error syncing club '.$club->name.' with hiking routes source ref: '.$club->cai_code);
                $this->error($e->getMessage());
            }

            if ($hikingRoutes->isNotEmpty()) {
                $hikingRoutesId = $hikingRoutes->pluck('id')->toArray();
                $club->hikingRoutes()->sync($hikingRoutesId);
                $this->info('Synced club '.$club->name.' with hiking routes source ref: '.$club->cai_code.PHP_EOL);
            }
        }

        $this->info('[END] Sync relationships between hiking routes and sections');
    }
}
