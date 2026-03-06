<?php

namespace App\Nova\Filters;

use App\Enums\UserRole;
use App\Models\Province;
use Laravel\Nova\Http\Requests\NovaRequest;

class ProvinceFilter extends BaseOSMFeaturesFilter
{
    public function __construct()
    {
        parent::__construct(__('Province'));
    }

    protected function getEmptyOptionLabel(): string
    {
        return __('No province');
    }

    protected function getEmptyOptionValue(): string
    {
        return 'no_province';
    }

    protected function getEntityOptions(NovaRequest $request): array
    {
        $options = [];
        $user = $request->user();
        if ($user && $user->hasRole(UserRole::RegionalReferent)) {
            $provinces = Province::where('region_id', $user->region->id)->orderBy('name')->get();
            foreach ($provinces as $item) {
                $options[$item->name] = $item->id;
            }
        } else {
            foreach (Province::orderBy('name')->get() as $item) {
                $options[$item->name] = $item->id;
            }
        }

        return $options;
    }

    protected function applyEmpty(NovaRequest $request, $query)
    {
        if ($query->getModel() instanceof \App\Models\HikingRoute) {
            return $query->whereDoesntHave('provinces');
        }

        if ($query->getModel() instanceof \App\Models\User) {
            return $query->whereDoesntHave('provinces');
        }

        return $query->whereNull('province_id');
    }

    protected function applyValue(NovaRequest $request, $query, $value)
    {
        if ($query->getModel() instanceof \App\Models\HikingRoute) {
            return $query->whereHas('provinces', function ($query) use ($value) {
                $query->where('province_id', $value);
            });
        }

        if ($query->getModel() instanceof \App\Models\User) {
            return $query->whereHas('provinces', function ($query) use ($value) {
                $query->where('name', Province::find($value)->name);
            });
        }

        return $query->where('province_id', $value);
    }
}
