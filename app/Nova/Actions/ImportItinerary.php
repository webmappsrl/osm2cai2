<?php

namespace App\Nova\Actions;

use Illuminate\Bus\Queueable;
use Laravel\Nova\Fields\Text;
use App\Http\Facades\OsmClient;
use App\Models\Itinerary;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Actions\Action;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Fields\ActionFields;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ImportItinerary extends Action
{
    use InteractsWithQueue, Queueable;

    public function __construct()
    {
        $this->name = __('Create itinerary and associate routes');
    }

    public function handle(ActionFields $fields, Collection $models)
    {
        if ($fields['ids']) {
            $ids = explode(',', $fields->ids);
        } else {
            return Action::danger(__('No IDs provided.'));
        }
        if (!$fields['itinerary_name']) {
            return Action::danger(__('No itinerary name provided.'));
        }
        $itinerary = Itinerary::firstOrCreate([
            'name' => $fields->itinerary_name,
        ]);

        try {
            if ($fields['import_source'] == 'OSM') {
                $hikingRoutes = DB::table('hiking_routes')->whereIn('relation_id', $ids)->get();
                $hikingRoutesIds = $hikingRoutes->pluck('id')->toArray();
                $itinerary->hikingRoutes()->attach($hikingRoutesIds);
                return Action::message(__('Itinerary created successfully!'));
            } else if ($fields['import_source'] == 'OSM2CAI') {
                $hikingRoutes = DB::table('hiking_routes')->whereIn('id', $ids)->get();
                $itinerary->hikingRoutes()->attach($ids);
                return Action::message(__('Itinerary created successfully!'));
            } else {
                return Action::danger(__('Invalid import source.'));
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return Action::danger(__('Error creating itinerary.'));
        }
    }

    public function fields($request)
    {
        return [
            Select::make(__('Source'), 'import_source')->options([
                'OSM' => __('OpenStreetMap'),
                'OSM2CAI' => __('OSM2CAI'),
            ])->rules('required'),

            Text::make(__('Route IDs'), 'ids')
                ->rules('required')
                ->help(__('Comma separated IDs e.g. 123,456,789')),

            Text::make(__('Itinerary Name'), 'itinerary_name')
                ->help(__('Name of the itinerary to create'))
        ];
    }
}
