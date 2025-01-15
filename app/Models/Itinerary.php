<?php

namespace App\Models;

use App\Models\HikingRoute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Itinerary extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'osm_id',
        'ref',
        'geometry',
    ];

    public function hikingRoutes()
    {
        return $this->belongsToMany(HikingRoute::class);
    }

    /**
     * Determines next and previous stage of each track inside the layer
     *
     * @return array
     */
    public function generateItineraryEdges(): ?array
    {
        $hikingRoutesIds = $this->hikingRoutes->pluck('id')->toArray();

        if (empty($this->hikingRoutes)) {
            return null;
        }

        $edges = [];

        foreach ($this->hikingRoutes as $hikingRoute) {
            // Recupera la geometria come WKT
            $geometry = DB::table('hiking_routes')
                ->selectRaw("ST_AsText(geometry) AS wkt")
                ->where('id', $hikingRoute->id)
                ->value('wkt');

            if (!$geometry) {
                continue; // Salta se la geometria non è valida
            }

            // Verifica il tipo di geometria
            $geometryType = DB::selectOne("
            SELECT ST_GeometryType(ST_GeomFromText(?, 4326)) AS type
        ", [$geometry])->type;

            if ($geometryType === 'ST_MultiLineString') {
                $geometry = DB::selectOne("
                SELECT ST_AsText(ST_LineMerge(ST_GeomFromText(?, 4326))) AS wkt
            ", [$geometry])->wkt;
            }

            // Estrai i punti iniziale e finale
            $startPoint = DB::selectOne("
            SELECT ST_AsText(ST_StartPoint(ST_GeomFromText(?, 4326))) AS wkt
        ", [$geometry])->wkt;

            $endPoint = DB::selectOne("
            SELECT ST_AsText(ST_EndPoint(ST_GeomFromText(?, 4326))) AS wkt
        ", [$geometry])->wkt;

            // Trova percorsi adiacenti
            $nextHikingRoute = HikingRoute::whereIn('id', $hikingRoutesIds)
                ->where('id', '<>', $hikingRoute->id)
                ->whereRaw("ST_DWithin(ST_SetSRID(geometry, 4326), ST_GeomFromText(?, 4326), 0.005)", [$endPoint])
                ->get();

            $previousHikingRoute = HikingRoute::whereIn('id', $hikingRoutesIds)
                ->where('id', '<>', $hikingRoute->id)
                ->whereRaw("ST_DWithin(ST_SetSRID(geometry, 4326), ST_GeomFromText(?, 4326), 0.005)", [$startPoint])
                ->get();

            $edges[$hikingRoute->id]['prev'] = $previousHikingRoute->pluck('id')->toArray();
            $edges[$hikingRoute->id]['next'] = $nextHikingRoute->pluck('id')->toArray();
        }

        return $edges;
    }


    /**
     * create the json for the itinerary
     *
     * @return array
     */
    public function generateItineraryJson(): ?array
    {
        $hikingRoutes = $this->hikingRoutes;
        $stages = count($hikingRoutes);
        $totalKm = 0;
        $hikingRoutesIds = $hikingRoutes->pluck('id')->toArray();

        if (empty($hikingRoutes)) {
            return null;
        }
        foreach ($hikingRoutes as $hikingRoute) {
            $totalKm += $hikingRoute->tdh['distance'] ?? 0;
        }

        $data = [];
        $data['type'] = 'Feature';
        $properties['id'] = $this->id;
        $properties['name'] = $this->name;
        $properties['stages'] = $stages;
        $properties['total_km'] = $totalKm;
        $properties['items'] = $hikingRoutesIds;
        $data['properties'] = $properties;

        return $data;
    }
}
