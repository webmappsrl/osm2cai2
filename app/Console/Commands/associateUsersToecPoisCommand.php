<?php

namespace App\Console\Commands;

use App\Models\EcPoi;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class associateUsersToecPoisCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:associate-users-to-ec-pois';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command import all users from osm2cai legacy database and associate them to ec_pois using api';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // get all ec_pois
        $ecPois = EcPoi::all();

        $progressBar = $this->output->createProgressBar($ecPois->count());
        $progressBar->start();

        foreach ($ecPois as $ecPoi) {
            $ecPoiApiData = Http::get('https://osm2cai.cai.it/api/v2/export/ec_pois/osmfeatures/'.$ecPoi->osmfeatures_id);

            if ($ecPoiApiData->failed() || $ecPoiApiData->json() === null) {
                $this->info('Failed to retrieve data from API: '.'https://osm2cai.cai.it/api/v2/export/ec_pois/osmfeatures/'.$ecPoi->osmfeatures_id);
                Log::error('Failed to retrieve data from API: '.'https://osm2cai.cai.it/api/v2/export/ec_pois/osmfeatures/'.$ecPoi->osmfeatures_id.' '.$ecPoiApiData->body());
            }

            $ecPoiApiData = $ecPoiApiData->json();

            // check if the user with $ecPoiApiData['user_id'] exists (ids are the same in both legacy and new database as previously imported with app/Console/Commands/SyncUsersFromLegacyOsm2cai.php)
            $user = User::where('id', $ecPoiApiData['user_id'])->first();

            if (! $user) {
                $this->info('User with id '.$ecPoiApiData['user_id'].' does not exist');

                continue;
            }

            $ecPoi->update(['user_id' => $ecPoiApiData['user_id']]);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
    }
}
