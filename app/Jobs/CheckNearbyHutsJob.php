<?php

namespace App\Jobs;

class CheckNearbyHutsJob extends CheckNearbyEntitiesJob
{
    protected function getTargetTableName(): string
    {
        return 'cai_huts';
    }

    protected function getRelationshipMethod(): string
    {
        return 'nearbyCaiHuts';
    }
}
