<?php

namespace App\Console\Commands;

use App\Models\UgcPoi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FixMissingUgcPoiFormIds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:fix-missing-form-ids';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command gets all ugc pois with missing form_id and updates them retrieving the form_id from the geohub api';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $pois = UgcPoi::whereNull('form_id')->get();

        foreach ($pois as $poi) {
            $geohubApiUrl = "http://host.docker.internal:8000/api/ugc/poi/geojson/{$poi->geohub_id}/osm2cai";
            $data = Http::get($geohubApiUrl);
            $data = json_decode($data->body(), true);
            $poi->raw_data = $data['properties']['raw_data'];
            $poi->form_id = $data['properties']['raw_data']['id'];
            $poi->saveQuietly();
        }
    }
}
