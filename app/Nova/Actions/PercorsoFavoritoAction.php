<?php

namespace App\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\Textarea;

class PercorsoFavoritoAction extends Action
{
    use InteractsWithQueue, Queueable;

    public $name;

    public function __construct()
    {
        $this->name = __('FAVORITE ROUTE');
    }

    public function handle(ActionFields $fields, Collection $models)
    {
        $user = auth()->user();

        if (! $user || $user == null) {
            return Action::danger('User information not available');
        }

        $model = $models->first();

        if (! $user->canManageHikingRoute($model)) {
            return Action::danger('You are not authorized for this hiking route');
        }

        // Handle FAVORITE toggle
        $model->region_favorite = $fields->favorite;
        $model->description_cai_it = $fields->description_cai_it;

        // Handle FEATURE IMAGE
        if ($fields->feature_image) {
            $model->addMedia($fields->feature_image)
                ->toMediaCollection('feature_image');
        }

        $model->save();

        return Action::message(__('Route updated successfully!'));
    }

    public function fields($request)
    {
        $id = $_REQUEST['resourceId'] ?? $_REQUEST['resources'] ?? null;
        $hr = \App\Models\HikingRoute::find(intval($id));
        $region_favorite = $hr->region_favorite ?? false;

        return [
            Boolean::make(__('Favorite Route'), 'favorite')
                ->default($region_favorite ?? false),
            Image::make(__('Image'), 'feature_image')
                ->help(__('For correct upload use files smaller than 20MB'))
                ->rules('image', 'max:2048'),
            Textarea::make(__('Description'), 'description_cai_it')
                ->rules('max:10000') // 10,000 characters limit
                ->default(\App\Models\HikingRoute::find(intval($id))->description_cai_it ?? ''),
        ];
    }
}
