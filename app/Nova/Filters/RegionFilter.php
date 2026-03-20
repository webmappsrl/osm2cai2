<?php

namespace App\Nova\Filters;

use App\Models\Club;
use App\Models\Province;
use App\Models\Region;
use App\Models\Sector;
use App\Models\User;
use Laravel\Nova\Http\Requests\NovaRequest;

class RegionFilter extends BaseOSMFeaturesFilter
{
    public function __construct()
    {
        parent::__construct(__('Region'));
    }

    protected function getEmptyOptionLabel(): string
    {
        return __('No region');
    }

    protected function getEmptyOptionValue(): string
    {
        return 'no_region';
    }

    protected function getEntityOptions(NovaRequest $request): array
    {
        $options = [];
        foreach (Region::all() as $region) {
            $options[$region->name] = $region->id;
        }

        return $options;
    }

    protected function applyEmpty(NovaRequest $request, $query)
    {
        $model = $query->getModel();

        if ($model instanceof Province || $model instanceof Club || $model instanceof User) {
            return $query->whereNull('region_id');
        }

        if ($model instanceof Sector) {
            return $query->whereHas('area.province', function ($query) {
                $query->whereNull('region_id');
            });
        }

        return $query->whereDoesntHave('regions');
    }

    protected function applyValue(NovaRequest $request, $query, $value)
    {
        $model = $query->getModel();

        if ($model instanceof Province || $model instanceof Club) {
            return $query->where('region_id', $value);
        }

        if ($model instanceof Sector) {
            return $query->whereHas('area.province', function ($query) use ($value) {
                $query->where('region_id', $value);
            });
        }

        if ($model instanceof User) {
            return $query->whereHas('region', function ($query) use ($value) {
                $query->where('region_id', $value);
            });
        }

        return $query->whereHas('regions', function ($query) use ($value) {
            $query->where('region_id', $value);
        });
    }
}
