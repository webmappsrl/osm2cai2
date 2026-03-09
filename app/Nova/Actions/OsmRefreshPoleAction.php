<?php

namespace App\Nova\Actions;

use App\Models\Poles;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use Wm\WmOsmfeatures\Exceptions\WmOsmfeaturesException;

class OsmRefreshPoleAction extends Action
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $showOnDetail = true;
    public $showOnIndex = false;

    public $name;

    public function __construct()
    {
        $this->name = __('Refresh from OSM (direct)');
    }

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        /** @var Poles $pole */
        $pole = $models->first();

        if (! $pole->osmfeatures_id) {
            return Action::danger(__('Pole does not have an osmfeatures_id.'));
        }

        try {
            Poles::refreshSingleFeatureFromOsm($pole->osmfeatures_id);
        } catch (WmOsmfeaturesException $e) {
            return Action::danger($e->getMessage());
        }

        return Action::redirect('/resources/poles/'.$pole->id);
    }

    /**
     * Get the fields available on the action.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [];
    }
}
