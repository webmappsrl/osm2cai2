<?php

namespace App\Nova\Actions;

use App\Models\Itinerary;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;

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
            $ids = explode(',', str_replace(' ', '', $fields->ids));
        } else {
            return Action::danger(__('No IDs provided.'));
        }
        if (! $fields['itinerary_name']) {
            return Action::danger(__('No itinerary name provided.'));
        }

        try {
            if ($fields['import_source'] == 'OSM') {
                $osmfeaturesIds = array_map(function ($id) {
                    return 'R'.$id;
                }, $ids);
                $hikingRoutes = DB::table('hiking_routes')->whereIn('osmfeatures_id', $osmfeaturesIds)->get();
                $hikingRoutesIds = $hikingRoutes->pluck('id')->toArray();

                if (count($hikingRoutesIds) > 0) {
                    $itinerary = Itinerary::firstOrCreate([
                        'name' => $fields->itinerary_name,
                    ]);
                    $itinerary->hikingRoutes()->attach($hikingRoutesIds);

                    return Action::message(__('Itinerary created successfully!'));
                } else {
                    return Action::danger(__('No hiking routes found with the provided IDs.'));
                }
            } elseif ($fields['import_source'] == 'OSM2CAI') {
                $hikingRoutes = DB::table('hiking_routes')->whereIn('id', $ids)->get();
                if (count($hikingRoutes) > 0) {
                    $itinerary = Itinerary::firstOrCreate([
                        'name' => $fields->itinerary_name,
                    ]);
                    $itinerary->hikingRoutes()->attach($ids);

                    return Action::message(__('Itinerary created successfully!'));
                } else {
                    return Action::danger(__('No hiking routes found with the provided IDs.'));
                }
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
                ->help(__('Name of the itinerary to create')),
        ];
    }
}
