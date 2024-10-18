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

    public function __construct($models)
    {
        $this->models = $models;
    }

    /**
     * Restituisce una collezione di dati da esportare.
     *
     * @return \Illuminate\Support\Collection
     */
    public function collection(): Collection
    {
        // Recupera i POI in base al form_id e calcola latitudine e longitudine usando PostGIS
        $pois = UgcPoi::whereIn('id', $this->models->pluck('id'));
        $pois->select('*');
        $pois->addSelect(DB::raw('ST_Y(ST_GeometryN(geometry::geometry, 1)) AS latitude'));
        $pois->addSelect(DB::raw('ST_X(ST_GeometryN(geometry::geometry, 1)) AS longitude'));
        $pois = $pois->get();

        // Mappa i POI per estrarre i campi necessari
        return $pois->map(function ($poi) {
            // Calcola la latitudine e la longitudine
            $lat = $poi->latitude ?? 'N/A';
            $lon = $poi->longitude ?? 'N/A';

            // Estrarre i campi comuni
            $commonFields = [
                'name' => $poi->user->name ?? 'N/A',
                'email' => $poi->user->email ?? 'N/A',
                'registered_at' => $poi->registered_at ?? 'N/A',
                'latitude' => $lat ?? 'N/A',
                'longitude' => $lon ?? 'N/A',
            ];

            return $commonFields;
        });
    }

    /**
     * Definisce le intestazioni delle colonne.
     *
     * @return array
     */
    public function headings(): array
    {
        return [
            'Nome utente',
            'Email utente',
            'Data di acquisizione',
            'Latitudine',
            'Longitudine',
        ];
    }


    /**
     * Applica stili al foglio di lavoro.
     *
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        // Applica l'allineamento a sinistra per tutte le celle
        $sheet->getStyle($sheet->calculateWorksheetDimension())
            ->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

        return [];
    }
}
