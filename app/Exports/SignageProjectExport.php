<?php

namespace App\Exports;

use App\Exports\Sheets\RoutesSheet;
use App\Exports\Sheets\SignageSheet;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Export wrapper per progetti segnaletica con due tab:
 * - Segnaletica: dati esistenti sui pali e frecce
 * - Percorsi: dati riepilogativi sui percorsi
 */
class SignageProjectExport implements WithMultipleSheets
{
    use Exportable;

    protected Collection $hikingRoutes;

    protected array $columns;

    protected array $styles;

    public function __construct(
        Collection $hikingRoutes,
        array $columns = [],
        array $styles = []
    ) {
        $this->hikingRoutes = $hikingRoutes;
        $this->columns = $columns;
        $this->styles = $styles;
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        return [
            new SignageSheet($this->hikingRoutes, $this->columns, $this->styles),
            new RoutesSheet($this->hikingRoutes),
        ];
    }
}
