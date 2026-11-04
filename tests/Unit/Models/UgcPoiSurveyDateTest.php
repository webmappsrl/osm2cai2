<?php

namespace Tests\Unit\Models;

use App\Models\UgcPoi;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * getSurveyDate() works on unsaved models: no database writes.
 */
class UgcPoiSurveyDateTest extends TestCase
{
    private function makeSurvey(array $properties = [], ?array $rawData = null, ?string $createdAt = null): UgcPoi
    {
        $poi = new UgcPoi;
        $poi->properties = array_merge(['form' => ['id' => 'water']], $properties);
        $poi->raw_data = $rawData;
        if ($createdAt !== null) {
            $poi->created_at = Carbon::parse($createdAt, 'Europe/Rome');
        }

        return $poi;
    }

    public function test_device_date_of_the_current_app_comes_first(): void
    {
        // survey 7252: both dates, properties.createdAt wins (decision of 04/11/2026)
        $poi = $this->makeSurvey(
            ['createdAt' => '2024-09-03T07:54:33.000000Z'],
            ['date' => '2024-09-02T07:42:40.896Z'],
            '2025-09-25 00:04:56'
        );

        $this->assertSame('2024-09-03T07:54:33+00:00', $poi->getSurveyDate()->toIso8601String());
    }

    public function test_falls_back_to_the_old_app_date(): void
    {
        $poi = $this->makeSurvey([], ['date' => '2024-08-11T12:46:11.655Z'], '2024-08-12 00:01:46');

        $this->assertSame('2024-08-11T12:46:11+00:00', $poi->getSurveyDate()->toIso8601String());
    }

    public function test_falls_back_to_created_at_converted_to_utc(): void
    {
        $poi = $this->makeSurvey([], ['flow_rate' => 'N/A'], '2025-05-02 10:00:00');

        $this->assertSame('2025-05-02T08:00:00+00:00', $poi->getSurveyDate()->toIso8601String());
    }

    public function test_blank_date_is_skipped_instead_of_becoming_now(): void
    {
        $poi = $this->makeSurvey(['createdAt' => ''], ['date' => '  '], '2025-05-02 10:00:00');

        $this->assertSame('2025-05-02T08:00:00+00:00', $poi->getSurveyDate()->toIso8601String());
    }

    public function test_non_iso_date_is_skipped(): void
    {
        // Carbon would read 11/08/2024 as November 8th
        $poi = $this->makeSurvey(['createdAt' => '11/08/2024'], ['date' => 'ieri'], '2025-05-02 10:00:00');

        $this->assertSame('2025-05-02T08:00:00+00:00', $poi->getSurveyDate()->toIso8601String());
    }

    public function test_unparsable_iso_like_date_is_skipped(): void
    {
        $poi = $this->makeSurvey(['createdAt' => '2024-13-45Tzz'], ['date' => '2024-08-11T12:46:11.655Z']);

        $this->assertSame('2024-08-11T12:46:11+00:00', $poi->getSurveyDate()->toIso8601String());
    }

    public function test_null_without_any_date(): void
    {
        $poi = $this->makeSurvey();

        $this->assertNull($poi->getSurveyDate());
    }
}
