<?php

namespace Tests\Unit\Models;

use App\Models\HikingRoute;
use App\Models\SiHikingRoute;
use App\Models\SiMTBRoute;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Wm\WmPackage\Models\Layer;

class HikingRouteMorphClassTest extends TestCase
{
    private const GEOM = "ST_GeomFromText('LINESTRINGZ(12.4924 41.8902 0, 12.4925 41.8903 0)', 4326)";

    /**
     * Crea una traccia app_id=2 con un layer associato via pivot polimorfico,
     * scritto con l'identita' che il database usa davvero: App\Models\HikingRoute.
     */
    private function createRouteWithLayer(): array
    {
        // withoutEvents: l'observer di EcTrack chiama il servizio DEM via HTTP
        // alla creazione, e qui interessa solo il record con il suo pivot.
        $route = HikingRoute::withoutEvents(fn () => HikingRoute::factory()->create([
            'app_id' => 2,
            'osm2cai_status' => 3,
            'geometry' => DB::raw(self::GEOM),
        ]));

        // properties e' NOT NULL senza default sulla tabella layers: va sempre passata
        $layer = Layer::create([
            'name' => 'Layer di test oc:8620',
            'properties' => [],
            'app_id' => 2,
        ]);

        DB::table('layerables')->insert([
            'layer_id' => $layer->id,
            'layerable_type' => 'App\Models\HikingRoute',
            'layerable_id' => $route->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$route, $layer];
    }

    /** @test */
    public function si_hiking_route_sees_the_same_layers_as_hiking_route()
    {
        [$route, $layer] = $this->createRouteWithLayer();

        $asParent = HikingRoute::find($route->id);
        $asChild = SiHikingRoute::find($route->id);

        $this->assertSame(
            $asParent->layers()->pluck('layers.id')->toArray(),
            $asChild->layers()->pluck('layers.id')->toArray(),
            'SiHikingRoute deve vedere gli stessi layer di HikingRoute sullo stesso record'
        );
        $this->assertContains($layer->id, $asChild->layers()->pluck('layers.id')->toArray());
    }

    /** @test */
    public function searchable_array_of_a_si_hiking_route_contains_the_layers()
    {
        [$route, $layer] = $this->createRouteWithLayer();

        $document = SiHikingRoute::find($route->id)->toSearchableArray();

        $this->assertContains(
            $layer->id,
            $document['layers'],
            "Il documento indicizzato di una SiHikingRoute deve contenere i layer: e' l'assert che avrebbe intercettato oc:8620"
        );
    }

    /** @test */
    public function searchable_array_of_a_si_mtb_route_contains_the_layers()
    {
        [$route, $layer] = $this->createRouteWithLayer();

        $document = SiMTBRoute::find($route->id)->toSearchableArray();

        $this->assertContains($layer->id, $document['layers']);
    }

    /** @test */
    public function saving_a_si_hiking_route_indexes_a_document_with_the_layers()
    {
        config(['scout.queue' => false]);

        [$route, $layer] = $this->createRouteWithLayer();

        $subject = SiHikingRoute::find($route->id);
        $subject->name = 'Tappa modificata da Nova oc:8620';
        $subject->save();

        $this->assertContains(
            $layer->id,
            $subject->fresh()->toSearchableArray()['layers'],
            'Dopo il salvataggio il documento deve conservare i layer'
        );
    }
}
