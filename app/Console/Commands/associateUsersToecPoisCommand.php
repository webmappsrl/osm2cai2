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
    protected $signature = 'osm2cai2:associate_users_to_ec_pois';

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
        //get all users by api and retrieve name, password and email. ID must be imported from osm2cai legacy (important for the upcoming association)
        $usersApiList = 'https://osm2cai.cai.it/api/v2/export/users/list';

        //make the call to api list
        $response = Http::get($usersApiList);

        if ($response->failed() || $response->json() === null) {
            $this->info('Failed to retrieve data from API: ' . $usersApiList);
            Log::error('Failed to retrieve data from API: ' . $usersApiList . ' ' . $response->body());

            return;
        }
        $list = $response->json();

        foreach ($list as $userId => $updated_at) {
            $userData = Http::get('https://osm2cai.cai.it/api/v2/export/users/' . $userId);

            if ($userData->failed() || $userData->json() === null) {
                $this->info('Failed to retrieve data from API: ' . 'https://osm2cai.cai.it/api/v2/export/users/' . $userId);
                Log::error('Failed to retrieve data from API: ' . 'https://osm2cai.cai.it/api/v2/export/users/' . $userId . ' ' . $userData->body());
                continue;
            }

            $userData = $userData->json();

            $id = $userData['id'];
            $name = $userData['name'];
            $password = bcrypt('webmapp123');
            $email = $userData['email'];

            if (User::where('email', $email)->first() === null) {
                $user = new User();
                $user->id = $id;
                $user->name = $name;
                $user->password = $password;
                $user->email = $email;
                $user->save();
            }
        }

        $this->info('Imported ' . count($list) . ' users');

        //get all ec_pois
        $ecPois = EcPoi::all();

        foreach ($ecPois as $ecPoi) {
            $ecPoiApiData = Http::get('https://osm2cai.cai.it/api/v2/export/ec_pois/osmfeatures/' . $ecPoi->osmfeatures_id);

            if ($ecPoiApiData->failed() || $ecPoiApiData->json() === null) {
                $this->info('Failed to retrieve data from API: ' . 'https://osm2cai.cai.it/api/v2/export/ec_pois/osmfeatures/' . $ecPoi->osmfeatures_id);
                Log::error('Failed to retrieve data from API: ' . 'https://osm2cai.cai.it/api/v2/export/ec_pois/osmfeatures/' . $ecPoi->osmfeatures_id . ' ' . $ecPoiApiData->body());
            }

            $ecPoiApiData = $ecPoiApiData->json();

            $ecPoi->update(['user_id' => $ecPoiApiData['user_id']]);
        }
    }
}
