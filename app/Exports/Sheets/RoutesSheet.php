<?php

namespace App\Exports\Sheets;

use App\Models\HikingRoute;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet per i percorsi con dati riepilogativi
 */
class RoutesSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected Collection $hikingRoutes;

    public function __construct(Collection $hikingRoutes)
    {
        $this->hikingRoutes = $hikingRoutes;
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Percorsi';
    }

    /**
     * @return Collection
     */
    public function collection(): Collection
    {
        // Carica le relazioni necessarie per evitare query N+1
        // loadMissing funziona solo su Collection di modelli Eloquent
        foreach ($this->hikingRoutes as $hikingRoute) {
            if (method_exists($hikingRoute, 'loadMissing')) {
                $hikingRoute->loadMissing(['regions', 'provinces']);
            }
        }

        return $this->hikingRoutes;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'CODICE REI',
            'REF',
            'Punto di Partenza',
            'Punto di Arrivo',
            'Regione',
            'Provincia',
            'Lunghezza in Km',
            'Dislivello positivo in m',
            'Dislivello negativo in m',
            'Tempo di percorrenza in avanti (HH:MM)',
            'Tempo di percorrenza all\'indietro (HH:MM)',
            'Link EDIT osm2CAI',
            'Link OSM',
        ];
    }

    /**
     * @param HikingRoute $hikingRoute
     * @return array
     */
    public function map($hikingRoute): array
    {
        if (! $hikingRoute instanceof HikingRoute) {
            return [];
        }

        // Recupera CODICE REI
        $codiceRei = $hikingRoute->getRefReiAttribute();

        // Recupera REF
        $osmfeaturesData = $this->parseOsmfeaturesData($hikingRoute->osmfeatures_data);
        $ref = $osmfeaturesData['properties']['ref'] ?? '';

        // Recupera Punto di Partenza e Arrivo
        // Usa direttamente i valori da osmfeatures_data per evitare errori SQL
        // che potrebbero abortire la transazione (getFromInfo/getToInfo usano query SQL
        // che falliscono con alcune geometrie di tipo geography)
        $puntoPartenza = $osmfeaturesData['properties']['from'] ?? '';
        $puntoArrivo = $osmfeaturesData['properties']['to'] ?? '';

        // Recupera Regione e Provincia
        $firstRegion = $hikingRoute->regions->first();
        $regione = '';
        
        if ($firstRegion) {
            $regione = $firstRegion->name;
        } else {
            // Fallback: recupera la regione dalla prima provincia se disponibile
            $firstProvince = $hikingRoute->provinces->first();
            if ($firstProvince && $firstProvince->relationLoaded('region')) {
                $regione = $firstProvince->region ? $firstProvince->region->name : '';
            } elseif ($firstProvince) {
                // Carica la relazione region se non è già caricata
                $firstProvince->load('region');
                $regione = $firstProvince->region ? $firstProvince->region->name : '';
            }
        }
        
        $firstProvince = $hikingRoute->provinces->first();
        $provincia = $firstProvince ? $firstProvince->name : '';

        // Recupera dati tecnici (distanza, dislivelli, tempi)
        $techData = $this->getTechData($hikingRoute);
        $lunghezzaKm = $techData['distance'];
        $dislivelloPositivo = $techData['ascent'];
        $dislivelloNegativo = $techData['descent'];
        $tempoAvanti = $techData['duration_forward'];
        $tempoIndietro = $techData['duration_backward'];

        // Recupera Link EDIT osm2CAI (cliccabile)
        $linkEditUrl = url('/resources/hiking-routes/' . $hikingRoute->id);
        $linkEdit = '=HYPERLINK("' . $linkEditUrl . '", "Modifica")';

        // Recupera Link OSM (cliccabile)
        $osmId = $osmfeaturesData['properties']['osm_id'] ?? null;
        if ($osmId) {
            $linkOsmUrl = 'https://www.openstreetmap.org/relation/' . $osmId;
            $linkOsm = '=HYPERLINK("' . $linkOsmUrl . '", "OSM")';
        } else {
            $linkOsm = '';
        }

        return [
            $codiceRei,
            $ref,
            $puntoPartenza,
            $puntoArrivo,
            $regione,
            $provincia,
            $lunghezzaKm,
            $dislivelloPositivo,
            $dislivelloNegativo,
            $tempoAvanti,
            $tempoIndietro,
            $linkEdit,
            $linkOsm,
        ];
    }

    /**
     * Recupera i dati tecnici del percorso
     * Cerca in ordine: tdh -> properties->dem_enrichment -> properties -> distance_comp
     *
     * @param HikingRoute $hikingRoute
     * @return array
     */
    protected function getTechData(HikingRoute $hikingRoute): array
    {
        $properties = $hikingRoute->properties ?? [];
        $tdh = $hikingRoute->tdh ?? [];

        // Prova prima da tdh
        if (! empty($tdh) && is_array($tdh)) {
            return [
                'distance' => $this->formatDistance($tdh['distance'] ?? null),
                'ascent' => $this->formatElevation($tdh['ascent'] ?? null),
                'descent' => $this->formatElevation($tdh['descent'] ?? null),
                'duration_forward' => $this->formatDuration($tdh['duration_forward'] ?? null),
                'duration_backward' => $this->formatDuration($tdh['duration_backward'] ?? null),
            ];
        }

        // Prova da properties->dem_enrichment
        if (! empty($properties['dem_enrichment']) && is_array($properties['dem_enrichment'])) {
            $dem = $properties['dem_enrichment'];
            return [
                'distance' => $this->formatDistance($dem['distance'] ?? null),
                'ascent' => $this->formatElevation($dem['ascent'] ?? null),
                'descent' => $this->formatElevation($dem['descent'] ?? null),
                'duration_forward' => $this->formatDuration($dem['duration_forward_hiking'] ?? $dem['duration_forward'] ?? null),
                'duration_backward' => $this->formatDuration($dem['duration_backward_hiking'] ?? $dem['duration_backward'] ?? null),
            ];
        }

        // Prova da properties direttamente
        if (! empty($properties)) {
            return [
                'distance' => $this->formatDistance($properties['distance'] ?? null),
                'ascent' => $this->formatElevation($properties['ascent'] ?? null),
                'descent' => $this->formatElevation($properties['descent'] ?? null),
                'duration_forward' => $this->formatDuration($properties['duration_forward'] ?? null),
                'duration_backward' => $this->formatDuration($properties['duration_backward'] ?? null),
            ];
        }

        // Fallback: usa distance_comp se disponibile
        $distanceComp = $hikingRoute->distance_comp ?? null;

        return [
            'distance' => $distanceComp !== null ? number_format($distanceComp, 2) : '',
            'ascent' => '',
            'descent' => '',
            'duration_forward' => '',
            'duration_backward' => '',
        ];
    }

    /**
     * Formatta la distanza in km
     *
     * @param mixed $distance
     * @return string
     */
    protected function formatDistance($distance): string
    {
        if ($distance === null || $distance === '' || $distance === 'Unknown') {
            return '';
        }

        // Se è già una stringa con "km", restituiscila
        if (is_string($distance) && strpos($distance, 'km') !== false) {
            return $distance;
        }

        // Converti in numero
        $distanceNum = is_numeric($distance) ? (float) $distance : null;

        if ($distanceNum === null) {
            return '';
        }

        // Se è maggiore di 1000, probabilmente è in metri, converti in km
        if ($distanceNum > 1000) {
            $distanceNum = $distanceNum / 1000;
        }

        // Formatta con 2 decimali
        return number_format($distanceNum, 2);
    }

    /**
     * Formatta il dislivello in metri
     *
     * @param mixed $elevation
     * @return string
     */
    protected function formatElevation($elevation): string
    {
        if ($elevation === null || $elevation === '' || $elevation === 'Unknown') {
            return '';
        }

        // Se è già una stringa, restituiscila
        if (is_string($elevation)) {
            return $elevation;
        }

        // Converti in numero e formatta
        $elevationNum = is_numeric($elevation) ? (int) $elevation : null;

        return $elevationNum !== null ? (string) $elevationNum : '';
    }

    /**
     * Formatta la durata in formato HH:MM
     *
     * @param mixed $duration
     * @return string
     */
    protected function formatDuration($duration): string
    {
        if ($duration === null || $duration === '' || $duration === 'Unknown') {
            return '';
        }

        // Se è già una stringa nel formato HH:MM, restituiscila
        if (is_string($duration) && preg_match('/^\d{1,2}:\d{2}$/', $duration)) {
            return $duration;
        }

        // Se è una stringa con formato diverso, prova a estrarre i minuti
        if (is_string($duration)) {
            // Cerca pattern come "h 2:30" o "2:30"
            if (preg_match('/(\d{1,2}):(\d{2})/', $duration, $matches)) {
                return $matches[1] . ':' . $matches[2];
            }
            // Cerca solo numeri (minuti)
            if (preg_match('/\d+/', $duration, $matches)) {
                $minutes = (int) $matches[0];
                return $this->minutesToHHMM($minutes);
            }
            return '';
        }

        // Se è un numero, assumiamo che siano minuti
        if (is_numeric($duration)) {
            return $this->minutesToHHMM((int) $duration);
        }

        return '';
    }

    /**
     * Converte minuti in formato HH:MM
     *
     * @param int $minutes
     * @return string
     */
    protected function minutesToHHMM(int $minutes): string
    {
        if ($minutes < 0) {
            return '';
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        return str_pad($hours, 2, '0', STR_PAD_LEFT) . ':' . str_pad($mins, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Parse osmfeatures_data in array
     *
     * @param mixed $osmfeaturesData
     * @return array
     */
    protected function parseOsmfeaturesData($osmfeaturesData): array
    {
        if (is_array($osmfeaturesData)) {
            return $osmfeaturesData;
        }

        if (is_string($osmfeaturesData)) {
            return json_decode($osmfeaturesData, true) ?? [];
        }

        return [];
    }

    /**
     * Apply styles to the worksheet
     *
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        $styles = [];

        // Stile per la riga di intestazione
        $headerStyle = [
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'], // Grigio chiaro
            ],
        ];

        $styles[1] = $headerStyle;

        return $styles;
    }
}
