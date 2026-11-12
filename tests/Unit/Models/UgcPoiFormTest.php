<?php

namespace Tests\Unit\Models;

use App\Models\UgcPoi;
use Tests\TestCase;

/**
 * forForm() and getFormValue(): no database writes (the query is only built, not run).
 */
class UgcPoiFormTest extends TestCase
{
    public function test_for_form_matches_the_column_or_properties_form_id(): void
    {
        $query = UgcPoi::forForm('water')->where('validated', 'valid');

        $sql = $query->toSql();

        // the two form conditions are grouped, so the validation filter stays in AND
        $this->assertMatchesRegularExpression('/where \("form_id" = \? or .*properties.*form.*id.*\) and "validated" = \?/i', $sql);
        $this->assertSame(['water', 'water', 'valid'], $query->getBindings());
    }

    public function test_form_value_reads_properties_form_first(): void
    {
        // survey 1831: the value corrected in Nova is in properties.form
        $poi = new UgcPoi;
        $poi->properties = ['form' => ['id' => 'water', 'conductivity' => '150']];
        $poi->raw_data = ['conductivity' => '1500'];

        $this->assertSame('150', $poi->getFormValue('conductivity'));
    }

    public function test_form_value_falls_back_to_raw_data_when_blank(): void
    {
        $poi = new UgcPoi;
        $poi->properties = ['form' => ['id' => 'water', 'temperature' => null, 'info' => ' ']];
        $poi->raw_data = ['temperature' => '10,4', 'info' => 'lavatoio'];

        $this->assertSame('10,4', $poi->getFormValue('temperature'));
        $this->assertSame('lavatoio', $poi->getFormValue('info'));
    }

    public function test_form_value_is_null_when_missing_everywhere(): void
    {
        $poi = new UgcPoi;
        $poi->properties = ['form' => ['id' => 'water']];
        $poi->raw_data = ['flow_rate' => 'N/A'];

        $this->assertNull($poi->getFormValue('temperature'));
    }
}
