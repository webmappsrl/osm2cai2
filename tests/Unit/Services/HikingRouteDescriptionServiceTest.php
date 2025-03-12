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
                'region_from' => 'Mountain Region',
            ],
            'to' => [
                'to' => 'Mountain Peak',
                'city_to' => 'Summit Town',
                'region_to' => 'Peak Region',
            ],
            'cai_scale' => [
                'it' => 'E',
                'en' => 'E',
                'es' => 'E',
                'de' => 'E',
                'fr' => 'E',
                'pt' => 'E',
            ],
            'tech' => [
                'distance' => 12.5,
                'ascent' => 800,
                'descent' => 300,
                'duration_forward' => 4.5,
                'duration_backward' => 3.0,
                'ele_min' => 1200,
                'ele_max' => 2000,
            ],
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
        $this->assertStringContainsString('The hiking trail ABC123 starts from Mountain Base in the municipality of Alpine Village (Mountain Region) and reaches Mountain Peak in the municipality of Summit Town (Peak Region).', $abstracts['en']);
        $this->assertStringContainsString('The path is classified as E with a total distance of 12.5 km and an elevation gain of 800 m uphill and 300 m downhill.', $abstracts['en']);
        $this->assertStringContainsString('The estimated walking time is 4.5 hours outbound and 3 hours return.', $abstracts['en']);
        $this->assertStringContainsString('The altitude varies from a minimum of 1200 m to a maximum of 2000 m above sea level.', $abstracts['en']);

        // Check Italian abstract content
        $this->assertStringContainsString('Il percorso escursionistico ABC123 parte da Mountain Base nel comune di Alpine Village (Mountain Region) e arriva a Mountain Peak nel comune di Summit Town (Peak Region).', $abstracts['it']);
        $this->assertStringContainsString('Il sentiero è classificato come E con una distanza totale di 12.5 km e un dislivello di 800 m in salita e 300 m in discesa.', $abstracts['it']);
        $this->assertStringContainsString('Il tempo di percorrenza stimato è di 4.5 ore in andata e 3 ore al ritorno.', $abstracts['it']);
        $this->assertStringContainsString('L\'altitudine varia da un minimo di 1200 m a un massimo di 2000 m sul livello del mare.', $abstracts['it']);

        // Check Spanish abstract content
        $this->assertStringContainsString('El sendero ABC123 parte de Mountain Base en el municipio de Alpine Village (Mountain Region) y llega a Mountain Peak en el municipio de Summit Town (Peak Region).', $abstracts['es']);
        $this->assertStringContainsString('El camino está clasificado como E con una distancia total de 12.5 km y un desnivel de 800 m de subida y 300 m de bajada.', $abstracts['es']);
        $this->assertStringContainsString('El tiempo estimado de caminata es de 4.5 horas de ida y 3 horas de vuelta.', $abstracts['es']);
        $this->assertStringContainsString('La altitud varía desde un mínimo de 1200 m hasta un máximo de 2000 m sobre el nivel del mar.', $abstracts['es']);

        // Check German abstract content
        $this->assertStringContainsString('Der Wanderweg ABC123 beginnt in Mountain Base in der Gemeinde Alpine Village (Mountain Region) und führt nach Mountain Peak in der Gemeinde Summit Town (Peak Region).', $abstracts['de']);
        $this->assertStringContainsString('Der Weg ist als E klassifiziert mit einer Gesamtdistanz von 12.5 km und einem Höhenunterschied von 800 m bergauf und 300 m bergab.', $abstracts['de']);
        $this->assertStringContainsString('Die geschätzte Gehzeit beträgt 4.5 Stunden hin und 3 Stunden zurück.', $abstracts['de']);
        $this->assertStringContainsString('Die Höhe variiert von minimal 1200 m bis maximal 2000 m über dem Meeresspiegel.', $abstracts['de']);

        // Check French abstract content
        $this->assertStringContainsString('Le sentier de randonnée ABC123 part de Mountain Base dans la commune de Alpine Village (Mountain Region) et arrive à Mountain Peak dans la commune de Summit Town (Peak Region).', $abstracts['fr']);
        $this->assertStringContainsString('Le sentier est classé comme E avec une distance totale de 12.5 km et un dénivelé de 800 m en montée et 300 m en descente.', $abstracts['fr']);
        $this->assertStringContainsString('Le temps de marche estimé est de 4.5 heures à l\'aller et 3 heures au retour.', $abstracts['fr']);
        $this->assertStringContainsString('L\'altitude varie d\'un minimum de 1200 m à un maximum de 2000 m au-dessus du niveau de la mer.', $abstracts['fr']);

        // Check Portuguese abstract content
        $this->assertStringContainsString('A trilha ABC123 parte de Mountain Base no município de Alpine Village (Mountain Region) e chega a Mountain Peak no município de Summit Town (Peak Region).', $abstracts['pt']);
        $this->assertStringContainsString('O caminho é classificado como E com uma distância total de 12.5 km e um desnível de 800 m de subida e 300 m de descida.', $abstracts['pt']);
        $this->assertStringContainsString('O tempo estimado de caminhada é de 4.5 horas na ida e 3 horas na volta.', $abstracts['pt']);
        $this->assertStringContainsString('A altitude varia de um mínimo de 1200 m a um máximo de 2000 m acima do nível do mar.', $abstracts['pt']);
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
                'region_from' => 'Woodland Region',
            ],
            'cai_scale' => [
                'it' => 'T',
                'en' => 'T',
                'es' => 'T',
                'de' => 'T',
                'fr' => 'T',
                'pt' => 'T',
            ],
            'tech' => [
                'distance' => 8.0,
                'ascent' => 350,
                'descent' => 350,
                'duration_forward' => 3.0,
                'ele_min' => 800,
                'ele_max' => 1150,
            ],
        ];

        $abstracts = $this->service->generateAbstract($data);

        $this->assertIsArray($abstracts);

        // Check English abstract content for loop trail
        $this->assertStringContainsString('The circular hiking trail LOOP456 has its starting and ending point at Trailhead in the municipality of Forest Town (Woodland Region).', $abstracts['en']);
        $this->assertStringContainsString('The path is classified as T with a total distance of 8 km and an elevation gain of 350 m uphill and 350 m downhill. ', $abstracts['en']);
        $this->assertStringContainsString('The estimated walking time is 3 hours.', $abstracts['en']);
        $this->assertStringContainsString('The altitude varies from a minimum of 800 m to a maximum of 1150 m above sea level.', $abstracts['en']);

        // Check Italian abstract content for loop trail
        $this->assertStringContainsString('Il percorso escursionistico ad anello', $abstracts['it']);
        $this->assertStringContainsString('Il sentiero è classificato come T con una distanza totale di 8 km e un dislivello di 350 m in salita e 350 m in discesa.', $abstracts['it']);
        $this->assertStringContainsString('Il tempo di percorrenza stimato è di 3 ore.', $abstracts['it']);
        $this->assertStringContainsString('L\'altitudine varia da un minimo di 800 m a un massimo di 1150 m sul livello del mare.', $abstracts['it']);

        // Check Spanish abstract content for loop trail
        $this->assertStringContainsString('El sendero circular LOOP456 tiene su punto de inicio y final en Trailhead en el municipio de Forest Town (Woodland Region).', $abstracts['es']);
        $this->assertStringContainsString('El camino está clasificado como T con una distancia total de 8 km y un desnivel de 350 m de subida y 350 m de bajada.', $abstracts['es']);
        $this->assertStringContainsString('El tiempo estimado de caminata es de 3 horas.', $abstracts['es']);
        $this->assertStringContainsString('La altitud varía desde un mínimo de 800 m hasta un máximo de 1150 m sobre el nivel del mar.', $abstracts['es']);

        // Check German abstract content for loop trail
        $this->assertStringContainsString('Der Rundwanderweg LOOP456 hat seinen Start- und Endpunkt in Trailhead in der Gemeinde Forest Town (Woodland Region).', $abstracts['de']);
        $this->assertStringContainsString('Der Weg ist als T klassifiziert mit einer Gesamtdistanz von 8 km und einem Höhenunterschied von 350 m bergauf und 350 m bergab.', $abstracts['de']);
        $this->assertStringContainsString('Die Höhe variiert von minimal 800 m bis maximal 1150 m über dem Meeresspiegel.', $abstracts['de']);

        // Check French abstract content for loop trail
        $this->assertStringContainsString('Le sentier de randonnée circulaire LOOP456 a son point de départ et d\'arrivée à Trailhead dans la commune de Forest Town (Woodland Region).', $abstracts['fr']);
        $this->assertStringContainsString('Le sentier est classé comme T avec une distance totale de 8 km et un dénivelé de 350 m en montée et 350 m en descente.', $abstracts['fr']);
        $this->assertStringContainsString('Le temps de marche estimé est de 3 heures.', $abstracts['fr']);
        $this->assertStringContainsString('L\'altitude varie d\'un minimum de 800 m à un maximum de 1150 m au-dessus du niveau de la mer.', $abstracts['fr']);

        // Check Portuguese abstract content for loop trail
        $this->assertStringContainsString('A trilha circular LOOP456 tem seu ponto de partida e chegada em Trailhead no município de Forest Town (Woodland Region).', $abstracts['pt']);
        $this->assertStringContainsString('O caminho é classificado como T com uma distância total de 8 km e um desnível de 350 m de subida e 350 m de descida.', $abstracts['pt']);
        $this->assertStringContainsString('O tempo estimado de caminhada é de 3 horas.', $abstracts['pt']);
        $this->assertStringContainsString('A altitude varia de um mínimo de 800 m a um máximo de 1150 m acima do nível do mar.', $abstracts['pt']);
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
                'region_from' => 'Region A',
            ],
            'to' => [
                'to' => 'End',
                'city_to' => 'City B',
                'region_to' => 'Region B',
            ],
            'cai_scale' => [
                'it' => 'Facile',
                'en' => 'Easy',
                'es' => 'Fácil',
                'de' => 'Einfach',
                'fr' => 'Facile',
                'pt' => 'Fácil',
            ],
            'tech' => [
                'distance' => 5.0,
                'ascent' => 200,
                'descent' => 200,
                'duration_forward' => 2.0,
                'duration_backward' => 1.5,
                'ele_min' => 500,
                'ele_max' => 700,
            ],
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
                'region_from' => 'Region',
            ],
            'to' => [
                // Missing 'to' field
                'city_to' => 'Destination',
                'region_to' => 'Region',
            ],
            'cai_scale' => [
                // Missing some languages
                'it' => 'E',
                'en' => 'E',
            ],
            'tech' => [
                'distance' => 10.0,
                'ascent' => 500,
                'descent' => 500,
                'duration_forward' => 3.5,
                // Missing duration_backward
                'ele_min' => 600,
                'ele_max' => 1100,
            ],
        ];

        $abstracts = $this->service->generateAbstract($data);

        $this->assertIsArray($abstracts);
        $this->assertArrayHasKey('en', $abstracts);
        $this->assertNotEmpty($abstracts['en']);
    }
}
