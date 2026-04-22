<?php

namespace App\Nova\Filters;

use App\Enums\UserRole;
use App\Models\Area;
use Laravel\Nova\Http\Requests\NovaRequest;

class AreaFilter extends BaseOSMFeaturesFilter
{
    public function __construct()
    {
        parent::__construct(__('Area'));
    }

    protected function getEmptyOptionLabel(): string
    {
        return __('No area');
    }

    protected function getEmptyOptionValue(): string
    {
        return 'no_area';
    }

    protected function getEntityOptions(NovaRequest $request): array
    {
        $options = [];
        $user = $request->user();
        if ($user && $user->hasRole(UserRole::RegionalReferent)) {
            $areas = Area::whereIn('province_id', $user->region->provinces->pluck('id')->toArray())->orderBy('name')->get();
            foreach ($areas as $item) {
                $options[$item->name] = $item->id;
            }
        } else {
            foreach (Area::orderBy('name')->get() as $item) {
                $options[$item->name] = $item->id;
            }
        }

        return $options;
    }

    protected function applyEmpty(NovaRequest $request, $query)
    {
        if ($query->getModel() instanceof \App\Models\HikingRoute) {
            return $query->whereDoesntHave('areas');
        }

        if ($query->getModel() instanceof \App\Models\User) {
            return $query->whereDoesntHave('areas');
        }

        return $query->whereNull('area_id');
    }

    protected function applyValue(NovaRequest $request, $query, $value)
    {
        if ($query->getModel() instanceof \App\Models\HikingRoute) {
            return $query->whereHas('areas', function ($query) use ($value) {
                $query->where('area_id', $value);
            });
        }

        if ($query->getModel() instanceof \App\Models\User) {
            return $query->whereHas('areas', function ($query) use ($value) {
                $query->where('area_id', $value);
            });
        }

        return $query->where('area_id', $value);
    }
}
