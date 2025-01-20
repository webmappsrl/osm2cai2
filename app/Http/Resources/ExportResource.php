<?php

namespace App\Http\Resources;

use App\Models\EcPoi;
use App\Models\HikingRoute;
use App\Models\Itinerary;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class ExportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $result = parent::toArray($request);

        $modelTable = $this->resource->getTable();

        if ($this->resource->geometry) {
            $geom = DB::select(
                "SELECT ST_AsGeoJSON(geometry) As geom FROM {$modelTable} WHERE id = :id",
                ['id' => $this->resource->id]
            );

            $result['geometry'] = json_decode($geom[0]->geom, true);
        }

        switch (true) {
            case $this->resource instanceof HikingRoute:
                $result['geometry_osm'] = json_decode($this->resource->geometry_osm, true);
                $result['geometry_raw_data'] = json_decode($this->resource->geometry_raw_data, true);
                $result['natural_springs'] = json_decode($this->resource->nearby_natural_springs, true);
                break;
            case $this->resource instanceof MountainGroup:
                $result['aggregated_data'] = json_decode($this->resource->aggregated_data, true);
                break;
            case $this->resource instanceof Itinerary:
                $result['edges'] = json_decode($this->resource->edges, true);
                break;
            case $this->resource instanceof EcPoi:
                $result['tags'] = is_string($this->osmfeatures_data) ? json_decode($this->osmfeatures_data, true)['properties']['osm_tags'] : $this->osmfeatures_data['properties']['osm_tags'];
                break;
            case $this->resource instanceof User:
                $result['password'] = $this->resource->password;
                break;
        }

        return $result;
    }
}
