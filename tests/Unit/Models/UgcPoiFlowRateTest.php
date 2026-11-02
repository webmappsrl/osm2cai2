<?php

namespace Tests\Unit\Models;

use App\Enums\ValidatedStatusEnum;
use App\Models\UgcPoi;
use Tests\TestCase;

/**
 * computeFlowRate() works on unsaved models: no database writes.
 */
class UgcPoiFlowRateTest extends TestCase
{
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
}
