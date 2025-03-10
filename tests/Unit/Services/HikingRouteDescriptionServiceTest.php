<?php

namespace Tests\Unit\Services;

use App\Services\HikingRouteDescriptionService;
use Tests\TestCase;

class HikingRouteDescriptionServiceTest extends TestCase
{
    private HikingRouteDescriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
        $this->service = new HikingRouteDescriptionService();
    }

    /** @test */
    public function it_returns_cai_scale_description_for_valid_scale()
    {
        $tDescription = $this->service->getCaiScaleDescription('T');
        $this->assertIsArray($tDescription);
        $this->assertArrayHasKey('it', $tDescription);
        $this->assertArrayHasKey('en', $tDescription);
        $this->assertArrayHasKey('es', $tDescription);
        $this->assertArrayHasKey('de', $tDescription);
        $this->assertArrayHasKey('fr', $tDescription);
        $this->assertArrayHasKey('pt', $tDescription);
        $this->assertStringContainsString('CARATTERISTICHE', $tDescription['it']);
        $this->assertStringContainsString('CHARACTERISTICS', $tDescription['en']);

        $eDescription = $this->service->getCaiScaleDescription('E');
        $this->assertIsArray($eDescription);
        $this->assertStringContainsString('sentieri o tracce', $eDescription['it']);
        
        $eeDescription = $this->service->getCaiScaleDescription('EE');
        $this->assertIsArray($eeDescription);
        $this->assertStringContainsString('terreno impervio', $eeDescription['it']);
    }

    /** @test */
    public function it_returns_fallback_description_for_invalid_scale()
    {
        $description = $this->service->getCaiScaleDescription('INVALID');
        $this->assertIsArray($description);
        $this->assertEquals('Difficoltà sconosciuta', $description['it']);
        $this->assertEquals('Unknown difficulty', $description['en']);
    }

    /** @test */
    public function it_generates_point_to_point_abstract_in_all_languages()
    {
        $data = [
            'roundtrip' => false,
            'ref' => 'ABC123',
            'from' => [
                'from' => 'Mountain Base',
                'city_from' => 'Alpine Village',
                'region_from' => 'Mountain Region'
            ],
            'to' => [
                'to' => 'Mountain Peak',
                'city_to' => 'Summit Town',
                'region_to' => 'Peak Region'
            ],
            'cai_scale' => [
                'it' => 'E',
                'en' => 'E',
                'es' => 'E',
                'de' => 'E',
                'fr' => 'E',
                'pt' => 'E'
            ],
            'tech' => [
                'distance' => 12.5,
                'ascent' => 800,
                'descent' => 300,
                'duration_forward' => 4.5,
                'duration_backward' => 3.0,
                'ele_min' => 1200,
                'ele_max' => 2000
            ]
        ];

        $abstracts = $this->service->generateAbstract($data);
        
        $this->assertIsArray($abstracts);
        $this->assertArrayHasKey('it', $abstracts);
        $this->assertArrayHasKey('en', $abstracts);
        $this->assertArrayHasKey('es', $abstracts);
        $this->assertArrayHasKey('de', $abstracts);
        $this->assertArrayHasKey('fr', $abstracts);
        $this->assertArrayHasKey('pt', $abstracts);
        
        // Check English abstract content
        $this->assertStringContainsString('ABC123', $abstracts['en']);
        $this->assertStringContainsString('Mountain Base', $abstracts['en']);
        $this->assertStringContainsString('Mountain Peak', $abstracts['en']);
        $this->assertStringContainsString('12.5 km', $abstracts['en']);
        $this->assertStringContainsString('800 m uphill', $abstracts['en']);
        
        // Check Italian abstract content
        $this->assertStringContainsString('ABC123', $abstracts['it']);
        $this->assertStringContainsString('Mountain Base', $abstracts['it']);
        $this->assertStringContainsString('12.5 km', $abstracts['it']);
    }

    /** @test */
    public function it_generates_loop_abstract_in_all_languages()
    {
        $data = [
            'roundtrip' => true,
            'ref' => 'LOOP456',
            'from' => [
                'from' => 'Trailhead',
                'city_from' => 'Forest Town',
                'region_from' => 'Woodland Region'
            ],
            'cai_scale' => [
                'it' => 'T',
                'en' => 'T',
                'es' => 'T',
                'de' => 'T',
                'fr' => 'T',
                'pt' => 'T'
            ],
            'tech' => [
                'distance' => 8.0,
                'ascent' => 350,
                'descent' => 350,
                'duration_forward' => 3.0,
                'ele_min' => 800,
                'ele_max' => 1150
            ]
        ];

        $abstracts = $this->service->generateAbstract($data);
        
        $this->assertIsArray($abstracts);
        
        // Check English abstract content for loop trail
        $this->assertStringContainsString('circular hiking trail', $abstracts['en']);
        $this->assertStringContainsString('LOOP456', $abstracts['en']);
        $this->assertStringContainsString('Trailhead', $abstracts['en']);
        $this->assertStringContainsString('Forest Town', $abstracts['en']);
        $this->assertStringContainsString('8 km', $abstracts['en']);
        
        // Check Italian abstract content for loop trail
        $this->assertStringContainsString('percorso escursionistico ad anello', $abstracts['it']);
    }

    /** @test */
    public function it_uses_current_locale_for_difficulty_text()
    {
        $data = [
            'roundtrip' => false,
            'ref' => 'TEST789',
            'from' => [
                'from' => 'Start',
                'city_from' => 'City A',
                'region_from' => 'Region A'
            ],
            'to' => [
                'to' => 'End',
                'city_to' => 'City B',
                'region_to' => 'Region B'
            ],
            'cai_scale' => [
                'it' => 'Facile',
                'en' => 'Easy',
                'es' => 'Fácil',
                'de' => 'Einfach',
                'fr' => 'Facile',
                'pt' => 'Fácil'
            ],
            'tech' => [
                'distance' => 5.0,
                'ascent' => 200,
                'descent' => 200,
                'duration_forward' => 2.0,
                'duration_backward' => 1.5,
                'ele_min' => 500,
                'ele_max' => 700
            ]
        ];

        $abstracts = $this->service->generateAbstract($data);
        $this->assertStringContainsString('Easy', $abstracts['en']);

        app()->setLocale('it');
        $abstracts = $this->service->generateAbstract($data);
        $this->assertStringContainsString('Facile', $abstracts['it']);
    }

    /** @test */
    public function it_handles_missing_data_gracefully()
    {
        $data = [
            'roundtrip' => false,
            'ref' => 'MINIMAL',
            'from' => [
                'from' => 'Start',
                'city_from' => 'City',
                'region_from' => 'Region'
            ],
            'to' => [
                // Missing 'to' field
                'city_to' => 'Destination',
                'region_to' => 'Region'
            ],
            'cai_scale' => [
                // Missing some languages
                'it' => 'E',
                'en' => 'E'
            ],
            'tech' => [
                'distance' => 10.0,
                'ascent' => 500,
                'descent' => 500,
                'duration_forward' => 3.5,
                // Missing duration_backward
                'ele_min' => 600,
                'ele_max' => 1100
            ]
        ];

        $abstracts = $this->service->generateAbstract($data);
        
        $this->assertIsArray($abstracts);
        $this->assertArrayHasKey('en', $abstracts);
        $this->assertNotEmpty($abstracts['en']);
    }
}