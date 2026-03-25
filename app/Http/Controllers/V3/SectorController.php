<?php

namespace App\Http\Controllers\V3;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;

class SectorController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v3/sectors/list",
     *     summary="Lista dei settori CAI",
     *     description="Restituisce l'elenco dei settori filtrati opzionalmente per data di aggiornamento e/o bounding box geografico.",
     *     tags={"Api V3"},
     *
     *     @OA\Parameter(
     *         name="updated_at",
     *         in="query",
     *         required=false,
     *         description="Filtra i settori aggiornati dopo questa data (formato YYYY-MM-DD)",
     *
     *         @OA\Schema(
     *             type="string",
     *             format="date",
     *             example="2024-05-01"
     *         )
     *     ),
     *
     *     @OA\Parameter(
     *         name="bbox",
     *         in="query",
     *         required=false,
     *         description="Bounding box geografico per filtrare i settori. Formato: min_lon,min_lat,max_lon,max_lat",
     *
     *         @OA\Schema(
     *             type="string",
     *             example="10.1,44.2,10.5,44.6"
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Lista dei settori trovati",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *                 type="object",
     *
     *                 @OA\Property(
     *                     property="id",
     *                     type="integer",
     *                     description="Identificatore univoco del settore",
     *                     example=3
     *                 ),
     *                 @OA\Property(
     *                     property="updated_at",
     *                     type="string",
     *                     format="date-time",
     *                     description="Data e ora dell'ultimo aggiornamento",
     *                     example="2024-05-01T10:00:00.000000Z"
     *                 ),
     *                 @OA\Property(
     *                     property="name",
     *                     type="object",
     *                     description="Nome del settore (translatable, lingua default: it)",
     *
     *                     @OA\Property(
     *                         property="it",
     *                         type="string",
     *                         example="Settore A"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Parametri non validi",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="error", type="string", example="Invalid bbox format. Expected: min_lon,min_lat,max_lon,max_lat")
     *         )
     *     )
     * )
     */
    public function list(Request $request): JsonResponse
    {
        $query = Sector::query();

        if ($request->filled('updated_at')) {
            $query->where('updated_at', '>=', Carbon::parse($request->input('updated_at'))->startOfDay());
        }

        if ($request->filled('bbox')) {
            $parts = explode(',', $request->input('bbox'));
            if (count($parts) !== 4) {
                return response()->json(['error' => 'Invalid bbox format. Expected: min_lon,min_lat,max_lon,max_lat'], 400);
            }
            [$minLon, $minLat, $maxLon, $maxLat] = $parts;
            $query->whereRaw(
                'ST_Intersects(geometry, ST_MakeEnvelope(?, ?, ?, ?, 4326))',
                [(float) $minLon, (float) $minLat, (float) $maxLon, (float) $maxLat]
            );
        }

        $sectors = $query->get(['id', 'updated_at', 'name'])->map(function ($sector) {
            return [
                'id' => $sector->id,
                'updated_at' => $sector->updated_at->toISOString(),
                'name' => ['it' => $sector->name],
            ];
        });

        return response()->json($sectors);
    }

    /**
     * @OA\Get(
     *     path="/api/v3/sectors/{id}",
     *     summary="Dettaglio di un settore CAI",
     *     description="Restituisce una GeoJSON Feature con la geometria e tutte le proprietà del settore identificato dall'id.",
     *     tags={"Api V3"},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Identificatore univoco del settore (ottenibile dalla API list)",
     *
     *         @OA\Schema(
     *             type="integer",
     *             example=3
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="GeoJSON Feature del settore",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="type", type="string", example="Feature"),
     *             @OA\Property(
     *                 property="geometry",
     *                 type="object",
     *                 description="Geometria MultiPolygon in formato GeoJSON (EPSG:4326)",
     *
     *                 @OA\Property(property="type", type="string", example="MultiPolygon"),
     *                 @OA\Property(property="coordinates", type="array", @OA\Items(type="array", @OA\Items(type="array", @OA\Items(type="array", @OA\Items(type="number")))))
     *             ),
     *             @OA\Property(
     *                 property="properties",
     *                 type="object",
     *                 description="Tutte le proprietà del settore",
     *
     *                 @OA\Property(property="id", type="integer", description="Identificatore univoco", example=3),
     *                 @OA\Property(
     *                     property="name",
     *                     type="object",
     *                     description="Nome del settore (translatable, lingua default: it)",
     *
     *                     @OA\Property(property="it", type="string", example="Settore A")
     *                 ),
     *                 @OA\Property(property="code", type="string", description="Codice breve del settore (1 carattere)", example="A"),
     *                 @OA\Property(property="full_code", type="string", description="Codice completo del settore (5 caratteri)", example="LIG01"),
     *                 @OA\Property(property="num_expected", type="integer", description="Numero di sentieri attesi nel settore", example=42),
     *                 @OA\Property(
     *                     property="human_name",
     *                     type="object",
     *                     nullable=true,
     *                     description="Nome leggibile del settore (translatable, lingua default: it)",
     *
     *                     @OA\Property(property="it", type="string", example="Settore Alpino Ligure")
     *                 ),
     *                 @OA\Property(property="manager", type="string", nullable=true, description="Responsabile del settore", example="Mario Rossi"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-04-26T06:24:21.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-20T14:22:00.000000Z")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Settore non trovato",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="error", type="string", example="Sector not found")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $sector = Sector::find($id);

        if (! $sector) {
            return response()->json(['error' => 'Sector not found'], 404);
        }

        $geomRow = DB::selectOne('SELECT ST_AsGeoJSON(geometry) as geom FROM sectors WHERE id = ?', [$id]);

        return response()->json([
            'type' => 'Feature',
            'geometry' => $geomRow ? json_decode($geomRow->geom) : null,
            'properties' => [
                'id' => $sector->id,
                'name' => ['it' => $sector->name],
                'code' => $sector->code,
                'full_code' => $sector->full_code,
                'num_expected' => $sector->num_expected,
                'human_name' => $sector->human_name !== null ? ['it' => $sector->human_name] : null,
                'manager' => $sector->manager,
                'created_at' => $sector->created_at?->toISOString(),
                'updated_at' => $sector->updated_at?->toISOString(),
            ],
        ]);
    }
}
