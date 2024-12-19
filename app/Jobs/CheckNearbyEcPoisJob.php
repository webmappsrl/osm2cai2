<?php

namespace App\Jobs;

class CheckNearbyEcPoisJob extends CheckNearbyEntitiesJob
{
    protected function getTargetTableName(): string
    {
        return 'ec_pois';
    }

    protected function getRelationshipMethod(): string
    {
        return 'nearbyEcPois';
    }
}
