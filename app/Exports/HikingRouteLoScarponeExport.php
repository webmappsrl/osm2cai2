<?php

namespace App\Exports;

use App\Models\HikingRoute;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromArray;

class HikingRouteLoScarponeExport implements FromArray
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function array(): array
    {
        $header = [
            'id',
            'region',
            'name',
            'cai_scale',
            'distance',
            'publish',
            'api',
            'map',
            'image',
            'osm'
        ];
        $results = [];
        $results[] = $header;
        $hrs = HikingRoute::where('region_favorite', true)->get();
        if (count($hrs) > 0) {
            foreach ($hrs as $hr) {
                $item = [
                    $hr->id,
                    implode(',', $hr->regions->pluck('name')->toArray()),
                    $hr->getNameForTDH()['it'],
                    $hr->osmfeatures_data['properties']['cai_scale'] ?? '',
                    $hr->distance_comp,
                    empty($hr->region_favorite_publication_date) ? 'NO PUBLICATION DATE' : $hr->region_favorite_publication_date,
                    route('hr_tdh_by_id', ['id' => $hr->id]),
                    route('hiking-route-public-map', ['id' => $hr->id]),
                    empty($hr->feature_image) ? 'NO IMAGE' : config('app.url') . Storage::url($hr->feature_image),
                    'https://openstreetmap.org/relation/' . $hr->osmfeatures_data['properties']['osm_id']
                ];
                $results[] = $item;
            }
        }
        return $results;
    }
}
