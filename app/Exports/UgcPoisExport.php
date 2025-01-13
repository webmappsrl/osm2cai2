<?php

namespace App\Exports;

use App\Models\UgcPoi;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UgcPoisExport implements FromCollection, WithHeadings, WithStyles
{
    protected $models;

    protected $fields;

    public function __construct(Collection $models, array $fields)
    {
        $this->models = $models;
        $this->fields = $fields;
    }

    public function collection(): Collection
    {
        return $this->models->map(function ($model) {
            $data = [];
            foreach ($this->fields as $field => $label) {
                $data[$field] = $this->getFieldValue($model, $field);
            }

            return $data;
        });
    }

    public function headings(): array
    {
        return array_values($this->fields);
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle($sheet->calculateWorksheetDimension())
            ->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

        return [];
    }

    protected function getFieldValue($model, $field)
    {
        //handle null values for latitude and longitude
        if ($field === 'raw_data->position->latitude') {
            $value = $this->getNestedValue($model, ['raw_data', 'position', 'latitude']);

            return $value ?? $model->getLatitude() ?? 'N/A';
        }

        if ($field === 'raw_data->position->longitude') {
            $value = $this->getNestedValue($model, ['raw_data', 'position', 'longitude']);

            return $value ?? $model->getLongitude() ?? 'N/A';
        }

        if (strpos($field, '->') !== false) {
            $parts = explode('->', $field);

            return $this->getNestedValue($model, $parts) ?? 'N/A';
        }

        return $model->$field ?? 'N/A';
    }

    protected function getNestedValue($model, array $parts)
    {
        $value = $model;
        foreach ($parts as $part) {
            $value = $value[$part] ?? $value->$part ?? null;
            if (is_null($value)) {
                return null;
            }
        }

        return $value;
    }
}
