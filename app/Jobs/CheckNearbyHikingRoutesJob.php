<?php

namespace App\Jobs;

class CheckNearbyHikingRoutesJob extends CheckNearbyEntitiesJob
{
    protected function getTargetTableName(): string
    {
        return 'hiking_routes';
    }

    protected function getRelationshipMethod(): string
    {
        return 'nearbyHikingRoutes';
    }
}
