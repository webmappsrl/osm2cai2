<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Wm\WmPackage\Services\Models\EcTrackService;

class ScoutImportEcTrack extends Command
{
    protected $signature = 'scout:import-ectrack';
    protected $description = 'Import EcTrack model into search index using EcTrackService';

    public function handle(EcTrackService $ecTrackService)
    {
        $modelClass = $ecTrackService->getModelClass();
        
        $this->call('scout:import', [$modelClass]);
    }
} 