<?php

namespace App\Nova\Actions;

use App\Models\UgcPoi;
use App\Models\UgcTrack;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;

class DeleteUgcMedia extends Action
{
    use Queueable;

    public $showOnDetail = true;

    public $showOnTableRow = true;

    protected $model;

    public function __construct($model = null)
    {
        $this->model = $model;
        $this->name = __('Delete Image');
    }

    public function handle(ActionFields $fields, Collection $models)
    {
        $model = $models->first();

        if (auth()->user()->id !== $model->user_id) {
            return Action::danger(__('You are not authorized to delete images for this resource.'));
        }

        $ugcMediaId = $fields->ugc_media_id;

        $ugcMedia = \App\Models\UgcMedia::find($ugcMediaId);
        if (!$ugcMedia) {
            return Action::danger(__('Image not found.'));
        }

        try {
            Storage::disk('public')->delete($ugcMedia->relative_url);

            // Dissociare l'immagine sia da ugc_poi che da ugc_track
            $ugcMedia->ugc_poi()->dissociate();
            $ugcMedia->ugc_track()->dissociate();
            $ugcMedia->save();

            $ugcMedia->delete();

            return Action::message(__('Image deleted successfully!'));
        } catch (\Exception $e) {
            return Action::danger(__('Error while deleting image: ') . $e->getMessage());
        }
    }

    public function fields(NovaRequest $request)
    {
        if (!$this->model) {
            return [];
        }

        $medias = $this->model->ugc_media()->get();
        $options = $medias->pluck('id', 'id');

        return [
            Select::make(__('Image'), 'ugc_media_id')
                ->options($options)
                ->rules('required')
                ->help(__('Select the ID of the image to delete.')),
        ];
    }
}
