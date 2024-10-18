<?php

namespace App\Exports;

use App\Models\UgcPoi;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
        if (strpos($field, '->') !== false) {
            $parts = explode('->', $field);
            $value = $model;
            foreach ($parts as $part) {
                $value = $value[$part] ?? $value->$part ?? null;
            }
            return $value ?? 'N/A';
        }
        return $model->$field ?? 'N/A';
    }
}
