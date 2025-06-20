<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ScoutImportEcTrack extends Command
{
    protected $signature = 'scout:import-ectrack';

    protected $description = 'Import EcTrack model into search index using EcTrackService';

    public function handle()
    {
        $modelClass = config('wm-package.ec_track_model');

        $this->call('scout:import', [$modelClass]);
    }
}
