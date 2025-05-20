<?php

namespace App\Jobs;

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
