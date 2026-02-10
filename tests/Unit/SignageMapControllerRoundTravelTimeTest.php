<?php

namespace Tests\Unit;

use Osm2cai\SignageMap\Http\Controllers\SignageMapController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class SignageMapControllerRoundTravelTimeTest extends TestCase
{
    /**
     * @dataProvider roundTravelTimeDataProvider
     */
    public function testRoundTravelTime(?int $input, ?int $expected): void
    {
        $controller = new SignageMapController();

        $refClass = new ReflectionClass($controller);
        $method = $refClass->getMethod('roundTravelTime');
        $method->setAccessible(true);

        $result = $method->invoke($controller, $input);

        $this->assertSame($expected, $result);
    }

    public static function roundTravelTimeDataProvider(): array
    {
        return [
            // Caso base / valori "non validi"
            'null stays null' => [null, null],
            'zero stays zero' => [0, 0],
            'negative stays negative' => [-10, -10],

            // Fascia 4–7 ore (verifica che non sia cambiata)
            '4h10 -> 4h30 (270)' => [4 * 60 + 10, 270],
            '6h30 -> 6h30 (390)' => [6 * 60 + 30, 390],
            '6h59 -> 7h00 (420)' => [6 * 60 + 59, 420],

            // Nuova regola dalle 7 ore in poi
            '7h00 exact' => [7 * 60, 7 * 60],                // 420 -> 420
            '7h04 -> 7h00' => [7 * 60 + 4, 7 * 60],          // 424 -> 420
            '7h05 -> 8h00' => [7 * 60 + 5, 8 * 60],          // 425 -> 480

            '8h00 exact' => [8 * 60, 8 * 60],                // 480 -> 480
            '8h04 -> 8h00' => [8 * 60 + 4, 8 * 60],          // 484 -> 480
            '8h05 -> 9h00' => [8 * 60 + 5, 9 * 60],          // 485 -> 540

            // Intorno alle 10 ore
            '10h00 exact' => [10 * 60, 10 * 60],             // 600 -> 600
            '10h04 -> 10h00' => [10 * 60 + 4, 10 * 60],      // 604 -> 600
            '10h05 -> 11h00' => [10 * 60 + 5, 11 * 60],      // 605 -> 660

            // Oltre le 10 ore
            '11h04 -> 11h00' => [11 * 60 + 4, 11 * 60],      // 664 -> 660
            '12h00 exact' => [12 * 60, 12 * 60],             // 720 -> 720
            '12h04 -> 12h00' => [12 * 60 + 4, 12 * 60],      // 724 -> 720
            '12h05 -> 13h00' => [12 * 60 + 5, 13 * 60],      // 725 -> 780
        ];
    }
}

