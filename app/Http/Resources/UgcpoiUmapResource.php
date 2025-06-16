<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UgcpoiUmapResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        switch ($this->form_id) {
            case 'poi':
                return $this->poiToArray();
            case 'signs':
                return $this->signsToArray();
            case 'archaeological_site':
                return $this->archaeologicalSiteToArray();
            case 'archaeological_area':
                return $this->archaeologicalAreaToArray();
            case 'geological_site':
                return $this->geologicalSiteToArray();
            default:
                return [];
        }
    }

    private function poiToArray()
    {
        return [
            'type' => 'Feature',
            'geometry' => $this->geometry ? json_decode(DB::select("SELECT ST_AsGeoJSON('$this->geometry')")[0]->st_asgeojson, true) : null,
            'properties' => [
                'title' => $this->name ?? $this->raw_data['title'] ?? '',
                'description' => $this->raw_data['description'] ?? $this->description ?? '',
                'waypointtype' => $this->raw_data['waypointtype'] ?? '',
                'validation_status' => $this->validated ?? '',
                'osm2cai_link' => url('resources/ugc-pois/' . $this->id),
                'images' => $this->ugc_media?->map(function ($image) {
                    $url = $image->getUrl();
                    if (strpos($url, 'http') === false) {
                        $url = Storage::disk('public')->url($url);
                    }

                    return $url;
                }),
            ],
        ];
    }

    private function signsToArray()
    {
        return [
            'type' => 'Feature',
            'geometry' => $this->geometry ? json_decode(DB::select("SELECT ST_AsGeoJSON('$this->geometry')")[0]->st_asgeojson, true) : null,
            'properties' => [
                'title' => $this->name ?? $this->raw_data['title'] ?? '',
                'artifact_type' => $this->raw_data['artifact_type'] ?? '',
                'location' => $this->raw_data['location'] ?? '',
                'conservation_status' => $this->raw_data['conservation_status'] ?? '',
                'notes' => $this->raw_data['notes'] ?? '',
                'validation_status' => $this->validated ?? '',
                'osm2cai_link' => url('resources/ugc-pois/' . $this->id),
                'images' => $this->ugc_media?->map(function ($image) {
                    $url = $image->getUrl();
                    if (strpos($url, 'http') === false) {
                        $url = Storage::disk('public')->url($url);
                    }

                    return $url;
                }),
            ],
        ];
    }

    private function archaeologicalSiteToArray()
    {
        return [
            'type' => 'Feature',
            'geometry' => $this->geometry ? json_decode(DB::select("SELECT ST_AsGeoJSON('$this->geometry')")[0]->st_asgeojson, true) : null,
            'properties' => [
                'title' => $this->name ?? $this->raw_data['title'] ?? '',
                'location' => $this->raw_data['location'] ?? '',
                'condition' => $this->raw_data['condition'] ?? '',
                'informational_supports' => $this->raw_data['informational_supports'] ?? '',
                'notes' => $this->raw_data['notes'] ?? '',
                'validation_status' => $this->validated ?? '',
                'osm2cai_link' => url('resources/ugc-pois/' . $this->id),
                'images' => $this->ugc_media?->map(function ($image) {
                    $url = $image->getUrl();
                    if (strpos($url, 'http') === false) {
                        $url = Storage::disk('public')->url($url);
                    }

                    return $url;
                }),
            ],
        ];
    }

    private function archaeologicalAreaToArray()
    {
        return [
            'type' => 'Feature',
            'geometry' => $this->geometry ? json_decode(DB::select("SELECT ST_AsGeoJSON('$this->geometry')")[0]->st_asgeojson, true) : null,
            'properties' => [
                'title' => $this->name ?? $this->raw_data['title'] ?? '',
                'area_type' => $this->raw_data['area_type'] ?? '',
                'location' => $this->raw_data['location'] ?? '',
                'notes' => $this->raw_data['notes'] ?? '',
                'validation_status' => $this->validated ?? '',
                'osm2cai_link' => url('resources/ugc-pois/' . $this->id),
                'images' => $this->ugc_media?->map(function ($image) {
                    $url = $image->getUrl();
                    if (strpos($url, 'http') === false) {
                        $url = Storage::disk('public')->url($url);
                    }

                    return $url;
                }),
            ],
        ];
    }

    private function geologicalSiteToArray()
    {
        return [
            'type' => 'Feature',
            'geometry' => $this->geometry ? json_decode(DB::select("SELECT ST_AsGeoJSON('$this->geometry')")[0]->st_asgeojson, true) : null,
            'properties' => [
                'title' => $this->name ?? $this->raw_data['title'] ?? '',
                'site_type' => $this->raw_data['site_type'] ?? '',
                'vulnerability' => $this->raw_data['vulnerability'] ?? '',
                'vulnerability_reasons' => $this->raw_data['vulnerability_reasons'] ?? '',
                'ispra_geosite' => $this->raw_data['ispra_geosite'] ?? '',
                'notes' => $this->raw_data['notes'] ?? '',
                'validation_status' => $this->validated ?? '',
                'osm2cai_link' => url('resources/ugc-pois/' . $this->id),
                'images' => $this->ugc_media?->map(function ($image) {
                    $url = $image->getUrl();
                    if (strpos($url, 'http') === false) {
                        $url = Storage::disk('public')->url($url);
                    }

                    return $url;
                }),
            ],
        ];
    }
}
