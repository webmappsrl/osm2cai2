<?php

namespace Tests\Unit\Models;

use App\Enums\ValidatedStatusEnum;
use App\Models\UgcPoi;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Unit\Services\FlowRateParserTest;

/**
 * computeFlowRate() works on unsaved models: no database writes.
 */
class UgcPoiFlowRateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // the expected reasons are the Italian translations
        app()->setLocale('it');
    }

    private function makeSurvey(array $form = [], ?array $rawData = null, string $validated = 'valid'): UgcPoi
    {
        $poi = new UgcPoi;
        $poi->properties = ['form' => array_merge(['id' => 'water'], $form)];
        $poi->raw_data = $rawData;
        $poi->water_flow_rate_validated = $validated;

        return $poi;
    }

    public function test_reads_volume_and_time_from_properties_form(): void
    {
        // survey 8621: data saved by the app through wm-package
        $poi = $this->makeSurvey(['range_volume' => '1', 'range_time' => '15'], ['flow_rate' => 'N/A']);

        $this->assertSame(0.067, $poi->computeFlowRate());
    }

    public function test_falls_back_to_raw_data_for_old_surveys(): void
    {
        $poi = $this->makeSurvey([], ['range_volume' => '0,5', 'range_time' => '5']);

        $this->assertSame(0.1, $poi->computeFlowRate());
    }

    public function test_properties_form_wins_over_raw_data(): void
    {
        $poi = $this->makeSurvey(
            ['range_volume' => '1', 'range_time' => '10'],
            ['range_volume' => '9', 'range_time' => '1'],
        );

        $this->assertSame(0.1, $poi->computeFlowRate());
    }

    public function test_empty_properties_form_value_falls_back_to_raw_data(): void
    {
        $poi = $this->makeSurvey(
            ['range_volume' => '', 'range_time' => '10'],
            ['range_volume' => '2'],
        );

        $this->assertSame(0.2, $poi->computeFlowRate());
    }

    public function test_raw_data_loaded_from_database_column_is_supported(): void
    {
        // raw_data arrives from the DB as a JSON string and is decoded by the array cast
        $poi = $this->makeSurvey();
        $poi->setRawAttributes(array_merge($poi->getAttributes(), [
            'raw_data' => json_encode(['range_volume' => '1', 'range_time' => '4']),
        ]));

        $this->assertSame(0.25, $poi->computeFlowRate());
    }

    public function test_not_valid_flow_rate_returns_na(): void
    {
        foreach ([ValidatedStatusEnum::INVALID, ValidatedStatusEnum::NOT_VALIDATED] as $status) {
            $poi = $this->makeSurvey(['range_volume' => '1', 'range_time' => '15'], null, $status->value);

            $this->assertSame('N/A', $poi->computeFlowRate());
        }
    }

    public function test_zero_time_returns_na(): void
    {
        $poi = $this->makeSurvey(['range_volume' => '1', 'range_time' => '0']);

        $this->assertSame('N/A', $poi->computeFlowRate());
    }

    public function test_not_interpretable_value_returns_na(): void
    {
        $poi = $this->makeSurvey(['range_volume' => '1 litro2', 'range_time' => '2"']);

        $this->assertSame('N/A', $poi->computeFlowRate());
    }

    public function test_milliliters_are_converted_to_liters(): void
    {
        // survey 4566: shown as 337.838 L/s before oc:8879
        $poi = $this->makeSurvey([], ['range_volume' => '500ml', 'range_time' => '1,48']);

        $this->assertSame(0.338, $poi->computeFlowRate());
    }

    public function test_missing_values_return_na(): void
    {
        $poi = $this->makeSurvey();

        $this->assertSame('N/A', $poi->computeFlowRate());
    }

    /**
     * Reasons shown in Nova next to N/A (oc:8879, second cycle).
     * Inputs come from real surveys of the local DB (id in the comment).
     */
    public static function reasonProvider(): array
    {
        return [
            'not validated' => [['range_volume' => '1', 'range_time' => '10'], null, ValidatedStatusEnum::NOT_VALIDATED->value, 'N/A (portata non validata)'],
            'not validated with data problems (8833)' => [['active' => 'yes'], null, ValidatedStatusEnum::NOT_VALIDATED->value, 'N/A (portata non validata; volume e tempo non inseriti)'],
            'source not active (1084)' => [['active' => 'no'], null, ValidatedStatusEnum::VALID->value, 'N/A (sorgente non attiva)'],
            'source not active but measured' => [['active' => 'no', 'range_volume' => '1', 'range_time' => '10'], null, ValidatedStatusEnum::VALID->value, 0.1],
            'source not active read from raw_data' => [[], ['active' => 'NO'], ValidatedStatusEnum::VALID->value, 'N/A (sorgente non attiva)'],
            'volume and time empty (1122)' => [['active' => 'yes'], null, ValidatedStatusEnum::VALID->value, 'N/A (volume e tempo non inseriti)'],
            'volume empty (1831)' => [['range_time' => '4'], null, ValidatedStatusEnum::VALID->value, 'N/A (volume non inserito)'],
            'time empty (1060)' => [['range_volume' => '1,5'], null, ValidatedStatusEnum::VALID->value, 'N/A (tempo non inserito)'],
            'volume not interpretable (1337)' => [['range_volume' => "0'.5", 'range_time' => '5.90'], null, ValidatedStatusEnum::VALID->value, 'N/A (volume non valido: inserire solo numeri)'],
            'time not interpretable (1166)' => [['range_volume' => '0,75', 'range_time' => 'Mezzo secondo'], null, ValidatedStatusEnum::VALID->value, 'N/A (tempo non valido: inserire solo numeri)'],
            'both not interpretable (4510)' => [['range_volume' => 'lt1..', 'range_time' => '1 secondo e 50 cent'], null, ValidatedStatusEnum::VALID->value, 'N/A (volume e tempo non validi: inserire solo numeri)'],
            'volume not interpretable and time empty (4610)' => [['range_volume' => "Impossibile determinazione stillicidio d'acqua"], null, ValidatedStatusEnum::VALID->value, 'N/A (volume non valido: inserire solo numeri; tempo non inserito)'],
            'volume empty and time not interpretable' => [['range_time' => 'Mezzo secondo'], null, ValidatedStatusEnum::VALID->value, 'N/A (volume non inserito; tempo non valido: inserire solo numeri)'],
            'source not active read from raw_data when blank in properties' => [['active' => ' '], ['active' => 'no'], ValidatedStatusEnum::VALID->value, 'N/A (sorgente non attiva)'],
            'zero time (1449)' => [['range_volume' => '0', 'range_time' => '0'], null, ValidatedStatusEnum::VALID->value, 'N/A (il tempo non può essere zero)'],
            'computable (197)' => [['range_volume' => '0,500', 'range_time' => '3'], null, ValidatedStatusEnum::VALID->value, 0.167],
            'blank properties value falls back to raw_data' => [['range_volume' => ' ', 'range_time' => '10'], ['range_volume' => '2'], ValidatedStatusEnum::VALID->value, 0.2],
        ];
    }

    #[DataProvider('reasonProvider')]
    public function test_flow_rate_for_display_explains_na(array $form, ?array $rawData, string $validated, float|string $expected): void
    {
        $poi = $this->makeSurvey($form, $rawData, $validated);

        $this->assertSame($expected, $poi->flowRateForDisplay());
    }

    /**
     * On every real value of the parser providers, a reason is shown if and only if
     * the flow rate is N/A, and otherwise the displayed value is the computed one.
     */
    public function test_reason_is_shown_if_and_only_if_flow_rate_is_na(): void
    {
        $cases = [];
        foreach (FlowRateParserTest::volumeProvider() as $name => [$volume]) {
            $cases["volume {$name}"] = ['range_volume' => $volume, 'range_time' => '10'];
        }
        foreach (FlowRateParserTest::timeProvider() as $name => [$time]) {
            $cases["time {$name}"] = ['range_volume' => '1', 'range_time' => $time];
        }

        foreach ($cases as $name => $form) {
            $poi = $this->makeSurvey($form);
            $value = $poi->computeFlowRate();
            $displayed = $poi->flowRateForDisplay();

            if ($value === 'N/A') {
                $this->assertIsString($displayed, $name);
                $this->assertStringStartsWith('N/A (', $displayed, $name);
            } else {
                $this->assertSame($value, $displayed, $name);
            }
        }
    }
}
