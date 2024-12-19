<?php

namespace App\Nova\Actions;

use Ebess\AdvancedNovaMediaLibrary\Fields\Images;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Http\Requests\NovaRequest;

class UploadAndAssociateUgcMedia extends Action
{
    use InteractsWithQueue, Queueable;

    public $showOnDetail = true;

    public $showOnTableRow = true;

    public function __construct()
    {
        $this->name = __('Upload Image');
    }

    public function handle(ActionFields $fields, Collection $models)
    {
        $model = $models->first();

        if (auth()->user()->id !== $model->user_id) {
            return Action::danger(__('You are not authorized to upload images for this model.'));
        }

        if (! $fields->has('ugc_media')) {
            return Action::danger(__('No image found in the request.'));
        }

        $ugcMedia = $fields->ugc_media;

        if (! $ugcMedia) {
            return Action::danger(__('The uploaded image is null.'));
        }

        \Log::info('Image details:', [
            'name' => $ugcMedia->getClientOriginalName(),
            'size' => $ugcMedia->getSize(),
            'mime' => $ugcMedia->getMimeType(),
        ]);

        if ($ugcMedia->getSize() > 10485760) {
            return Action::danger(__('The uploaded image exceeds the maximum allowed size'));
        }

        try {
            $path = $ugcMedia->store('ugc-media', 'public');

            $geometry = $model->geometry;
            $newUgcMedia = \App\Models\UgcMedia::create([
                'name' => $ugcMedia->getClientOriginalName(),
                'relative_url' => 'ugc-media/'.basename($path),
                'user_id' => auth()->user()->id,
                'geometry' => $geometry,
                'app_id' => 'osm2cai',
            ]);
            $model->ugc_media()->save($newUgcMedia);

            return Action::message(__('Image uploaded and associated successfully!'));
        } catch (\Exception $e) {
            return Action::danger(__('Error during image upload: ').$e->getMessage());
        }
    }

    public function fields(NovaRequest $request)
    {
        return [
            File::make(__('Image'), 'ugc_media')
                ->disk('public')
                ->path('ugc-media')
                ->store(function ($request, $model) {
                    return $request->file('ugc-media')->store('ugc-media', 'public');
                })
                ->help(__('Upload an image to associate with the POI. Allowed size: max 10MB')),
        ];
    }
}
