<?php

namespace Tests\Unit\Models;

use App\Models\HikingRoute;
use Carbon\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class HikingRouteGeojsonDescriptionTest extends TestCase
{
    private function callEnhanceHikingRouteProperties(HikingRoute $route, array $baseGeojson): array
    {
        $method = new ReflectionMethod(HikingRoute::class, 'enhanceHikingRouteProperties');
        $method->setAccessible(true);

        /** @var array $result */
        $result = $method->invoke($route, $baseGeojson);

        return $result;
    }

    public function test_uses_sicai_percorribilita_and_sicai_data_when_present(): void
    {
        $route = new HikingRoute();
        $route->properties = [
            'sicai' => [
                'percorribilità' => '<b>OK</b>',
                'data' => '01/02/2026',
            ],
        ];

        $out = $this->callEnhanceHikingRouteProperties($route, [
            'type' => 'Feature',
            'properties' => [],
            'geometry' => null,
        ]);

        $this->assertIsArray($out['properties']['description']);
        $this->assertArrayHasKey('it', $out['properties']['description']);

        // percorribilita is escaped via e()
        $this->assertStringContainsString('Percorribilità:', $out['properties']['description']['it']);
        $this->assertStringContainsString('&lt;b&gt;OK&lt;/b&gt;', $out['properties']['description']['it']);

        // data is injected as-is
        $this->assertStringContainsString('Ultimo aggiornamento:', $out['properties']['description']['it']);
        $this->assertStringContainsString('01/02/2026', $out['properties']['description']['it']);
    }

    public function test_falls_back_to_issues_fields_when_sicai_fields_are_missing(): void
    {
        $route = new HikingRoute();
        $route->properties = ['sicai' => []];
        $route->issues_status = 'Praticabile';
        $route->issues_last_update = Carbon::create(2026, 4, 22);

        $out = $this->callEnhanceHikingRouteProperties($route, [
            'type' => 'Feature',
            'properties' => [],
            'geometry' => null,
        ]);

        $this->assertStringContainsString('Percorribilità:', $out['properties']['description']['it']);
        $this->assertStringContainsString('Praticabile', $out['properties']['description']['it']);
        $this->assertStringContainsString('Ultimo aggiornamento:', $out['properties']['description']['it']);
        $this->assertStringContainsString('22/04/2026', $out['properties']['description']['it']);
    }

    public function test_prefers_sicai_values_over_issues_values_when_both_are_present(): void
    {
        $route = new HikingRoute();
        $route->properties = [
            'sicai' => [
                'percorribilità' => 'Da SICAi',
                'data' => '10/03/2026',
            ],
        ];
        $route->issues_status = 'Da Issues';
        $route->issues_last_update = Carbon::create(2026, 4, 22);

        $out = $this->callEnhanceHikingRouteProperties($route, [
            'type' => 'Feature',
            'properties' => [],
            'geometry' => null,
        ]);

        $this->assertStringContainsString('Percorribilità:', $out['properties']['description']['it']);
        $this->assertStringContainsString('Da SICAi', $out['properties']['description']['it']);
        $this->assertStringNotContainsString('Da Issues', $out['properties']['description']['it']);

        $this->assertStringContainsString('Ultimo aggiornamento:', $out['properties']['description']['it']);
        $this->assertStringContainsString('10/03/2026', $out['properties']['description']['it']);
        $this->assertStringNotContainsString('22/04/2026', $out['properties']['description']['it']);
    }

    public function test_does_not_add_percorribilita_block_when_no_value_is_available(): void
    {
        $route = new HikingRoute();
        $route->properties = [];
        $route->issues_status = null;
        $route->issues_last_update = null;

        $out = $this->callEnhanceHikingRouteProperties($route, [
            'type' => 'Feature',
            'properties' => [],
            'geometry' => null,
        ]);

        $this->assertArrayHasKey('description', $out['properties']);
        $this->assertArrayHasKey('it', $out['properties']['description']);
        $this->assertStringNotContainsString('Percorribilità:', $out['properties']['description']['it']);
        $this->assertStringNotContainsString('Ultimo aggiornamento:', $out['properties']['description']['it']);
    }
}

