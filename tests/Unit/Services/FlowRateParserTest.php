<?php

namespace Tests\Unit\Services;

use App\Services\FlowRateParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The provider values are the non numeric volume/time values found in the
 * validated water source surveys (oc:8879), plus the clean cases.
 * Add here any new real value that turns out to be parsed wrongly.
 */
class FlowRateParserTest extends TestCase
{
    public static function volumeProvider(): array
    {
        return [
            // clean values
            ['1', 1.0],
            ['0.75', 0.75],
            ['0,75', 0.75],
            ['.6', 0.6],
            [1, 1.0],
            [0.5, 0.5],
            // space after the decimal separator
            ['0, 5', 0.5],
            ['1, 5', 1.5],
            // letter o used as zero
            ['o.5', 0.5],
            ['O,5', 0.5],
            ['o,500', 0.5],
            // liters
            ['1 litro', 1.0],
            ['1litro', 1.0],
            ['1l', 1.0],
            ['1 lt', 1.0],
            ['1 lt.', 1.0],
            ['0.500 l', 0.5],
            ['0,500 litri', 0.5],
            ['0,5 lt', 0.5],
            ['1,5 l', 1.5],
            // milliliters
            ['500ml', 0.5],
            ['500 ml', 0.5],
            // not interpretable
            ['NA', null],
            ['0 45', null],
            ["0'.5", null],
            ['1 litro2', null],
            ['lt1..', null],
            ["Impossibile determinazione stillicidio d'acqua", null],
            ['', null],
            ['   ', null],
            [null, null],
        ];
    }

    public static function timeProvider(): array
    {
        return [
            // clean values
            ['15', 15.0],
            ['9.5', 9.5],
            ['3.5"', 3.5],
            [5, 5.0],
            // seconds with quotes
            ['15"', 15.0],
            ['6"', 6.0],
            ['3"', 3.0],
            ['9"', 9.0],
            ['2"', 2.0],
            ['5"', 5.0],
            ['7"', 7.0],
            ['12"', 12.0],
            ['27"', 27.0],
            ['11"', 11.0],
            ['25"', 25.0],
            ['29"', 29.0],
            ['30"', 30.0],
            ["17''", 17.0],
            ["1,8''", 1.8],
            ["03''", 3.0],
            ["2''", 2.0],
            ["3''", 3.0],
            ["5''", 5.0],
            ["7''", 7.0],
            ["8''", 8.0],
            ["30''", 30.0],
            // single quote and ™ typed instead of the seconds sign
            ["11'", 11.0],
            ['6™', 6.0],
            // seconds written as words
            ['10 secondi', 10.0],
            ['secondi 3.85', 3.85],
            // space after the decimal separator
            ['1, 46', 1.46],
            ['19, 6', 19.6],
            // mm:ss and mm:ss.cc
            ['00:05.65', 5.65],
            ['01:05.3', 65.3],
            // mm ss cc
            ['00 01 73', 1.73],
            ['00 02 53', 2.53],
            ['00 05 02', 5.02],
            ['00 11 98', 11.98],
            ['00 01 00', 1.0],
            // not interpretable
            ['00 06. 79', null],
            ['7s 80', null],
            ['1 secondo e 50 cent', null],
            ['Mezzo secondo', null],
            ['NA', null],
            ['NÀ', null],
            ['', null],
            [null, null],
        ];
    }

    #[DataProvider('volumeProvider')]
    public function test_parse_volume(mixed $input, ?float $expected): void
    {
        $this->assertParsed($expected, FlowRateParser::parseVolume($input));
    }

    #[DataProvider('timeProvider')]
    public function test_parse_time(mixed $input, ?float $expected): void
    {
        $this->assertParsed($expected, FlowRateParser::parseTime($input));
    }

    private function assertParsed(?float $expected, ?float $actual): void
    {
        if ($expected === null) {
            $this->assertNull($actual);

            return;
        }

        $this->assertNotNull($actual);
        $this->assertEqualsWithDelta($expected, $actual, 0.0001);
    }
}
