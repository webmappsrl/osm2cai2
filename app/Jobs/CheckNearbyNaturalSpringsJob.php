<?php

namespace App\Jobs;

use App\Models\HikingRoute;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckNearbyNaturalSpringsJob extends CheckNearbyEntitiesJob
{
    protected function getTargetTableName(): string
    {
        return 'natural_springs';
    }

    protected function getRelationshipMethod(): string
    {
        return 'nearbyNaturalSprings';
    }
}
