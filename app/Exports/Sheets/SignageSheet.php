<?php

namespace App\Exports\Sheets;

use App\Exports\HikingRouteSignageExporter;
use App\Models\HikingRoute;
use App\Models\Poles;
use App\Nova\Poles as PolesResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Wm\WmPackage\Exporters\ModelExporter;

/**
 * Sheet per la segnaletica (pali e frecce)
 * Estratto da HikingRouteSignageExporter per essere usato come sheet in un export multi-tab
 */
class SignageSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected Collection $hikingRoutes;

    protected array $columns;

    protected array $styles;

    protected array $expandedData = [];

    protected array $poleCoordinates = [];

    protected array $hikingRouteDataCache = [];

    // Intestazioni delle colonne
    protected const HEADER_ROW_1 = [
        'Palo',
        'Soggetto manutentore',
        'N. Sent.',
        'Ldp n.',
        'tab n.',
        'Sentiero',
        'Logo lungo it',
        'Meta 1',
        'Ore 1',
        'Meta 2',
        'Ore 2',
        'Meta 3',
        'Ore 3',
        'Dir.',
        'Località 1',
        'Località 2',
        'Quota',
        'Codice Ldp',
    ];

    protected const HEADER_ROW_2 = [
        '',
        'Soggetto finanziatore',
        'Logo 1 meta 1',
        'Logo 2 meta 1',
        'Logo 1 meta 2',
        'Logo 2 meta 2',
        'Logo 1 meta 3',
        'Info meta 1',
        'Km 1',
        'Info meta 2',
        'Km 2',
        'Info meta 3',
        'Km 3',
        'Logo 2 meta 3',
        'Latutudine',
        'Longitudine',
        'QR code',
        'Codice Tabella',
    ];

    public function __construct(
        Collection $hikingRoutes,
        array $columns = [],
        array $styles = ModelExporter::DEFAULT_STYLE
    ) {
        $this->hikingRoutes = $hikingRoutes;
        $this->columns = $columns;
        $this->styles = $styles;
        $this->prepareExpandedData();
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Segnaletica';
    }

    /**
     * Prepara i dati espansi per ogni HikingRoute con i suoi Poles e le frecce
     * Ordinati secondo points_order e arrow_order
     */
    protected function prepareExpandedData(): void
    {
        $this->expandedData = [];

        // Carica le relazioni per ogni HikingRoute nella Collection
        foreach ($this->hikingRoutes as $hikingRoute) {
            if ($hikingRoute instanceof HikingRoute) {
                $hikingRoute->loadMissing('clubs', 'areas');
            }
        }

        // Crea una mappa delle HikingRoute per recupero rapido per ID
        $hikingRouteMap = [];
        foreach ($this->hikingRoutes as $hikingRoute) {
            $this->cacheHikingRouteData($hikingRoute);
            $hikingRouteMap[(string) $hikingRoute->id] = $hikingRoute;
        }

        // Pre-calcola i poles ordinati e le posizioni per ogni HikingRoute (ottimizzazione performance)
        $hikingRoutePolesCache = [];
        $hikingRoutePolePositionsCache = [];
        foreach ($this->hikingRoutes as $hikingRoute) {
            $poles = $hikingRoute->getPolesWithBuffer();
            $hrData = $this->hikingRouteDataCache[$hikingRoute->id] ?? [];
            $pointsOrder = $hrData['points_order'] ?? null;
            $orderedPoles = $this->orderPolesByPointsOrder($poles, $pointsOrder);

            $hikingRoutePolesCache[(string) $hikingRoute->id] = $orderedPoles;

            // Crea una mappa pole_id -> posizione per accesso rapido
            $polePositions = [];
            foreach ($orderedPoles as $pos => $pole) {
                $polePositions[(string) $pole->id] = $pos;
            }
            $hikingRoutePolePositionsCache[(string) $hikingRoute->id] = $polePositions;
        }

        // Traccia i poles già processati per evitare duplicati
        $processedPoles = [];

        foreach ($this->hikingRoutes as $hikingRoute) {
            // Recupera i Poles per questa HikingRoute dalla cache
            $orderedPoles = $hikingRoutePolesCache[(string) $hikingRoute->id] ?? [];

            // Per ogni pole ordinato
            foreach ($orderedPoles as $poleIndex => $pole) {
                // Salta se il pole è già stato processato
                $poleId = (string) $pole->id;
                if (isset($processedPoles[$poleId])) {
                    continue;
                }

                // Salta il palo (e le sue frecce) se è in export_ignore per questa HikingRoute: non esportare nell'export CSV/Excel, preservando la numerazione di posizione
                $hrProperties = $hikingRoute->properties ?? [];
                $exportIgnorePoleIds = $hrProperties['signage']['export_ignore'] ?? [];
                $isExportIgnored = false;
                foreach ($exportIgnorePoleIds as $ignoredId) {
                    if ((int) $ignoredId === (int) $pole->id || (string) $ignoredId === $poleId) {
                        $isExportIgnored = true;
                        break;
                    }
                }
                if ($isExportIgnored) {
                    continue;
                }

                // Segna il pole come processato
                $processedPoles[$poleId] = true;
                // Recupera arrow_order dal pole (contiene tutte le arrows con formato routeId-index)
                $poleProperties = $pole->properties ?? [];
                $signage = $poleProperties['signage'] ?? [];

                // Recupera arrow_order che contiene tutte le arrows
                $arrowOrder = $signage['arrow_order'] ?? [];

                if (empty($arrowOrder)) {
                    continue;
                }

                // Per ogni entry in arrow_order, recupera l'arrow corrispondente
                foreach ($arrowOrder as $positionInOrder => $arrowKey) {
                    // arrowKey ha formato "routeId-index"
                    $parts = explode('-', $arrowKey, 2);
                    if (count($parts) !== 2) {
                        continue;
                    }

                    $arrowRouteId = $parts[0];
                    $arrowIndex = (int) $parts[1];

                    // Recupera la HikingRoute a cui appartiene l'arrow
                    if (! isset($hikingRouteMap[$arrowRouteId])) {
                        continue;
                    }

                    $arrowHikingRoute = $hikingRouteMap[$arrowRouteId];
                    $this->cacheHikingRouteData($arrowHikingRoute);

                    // Verifica che esista la struttura per questa routeId
                    if (! isset($signage[$arrowRouteId]) || ! is_array($signage[$arrowRouteId])) {
                        continue;
                    }

                    $routeSignage = $signage[$arrowRouteId];
                    $arrows = $routeSignage['arrows'] ?? [];

                    // Verifica che esista l'arrow all'indice specificato
                    if (! isset($arrows[$arrowIndex])) {
                        continue;
                    }

                    $arrow = $arrows[$arrowIndex];
                    $direction = $arrow['direction'] ?? 'forward';
                    $rows = $arrow['rows'] ?? [];

                    if (empty($rows)) {
                        continue;
                    }

                    // Calcola ldpN usando la cache delle posizioni dei poles (ottimizzazione performance)
                    $polePositions = $hikingRoutePolePositionsCache[$arrowRouteId] ?? [];
                    $polePosition = $polePositions[$poleId] ?? null;

                    // Se il pole non è trovato, usa l'indice corrente come fallback
                    $ldpN = $polePosition !== null ? $this->formatLdpN($polePosition + 1) : $this->formatLdpN(1);

                    // Calcola tab_n in base alla posizione in arrow_order (1-based)
                    $tabN = (string) ($positionInOrder + 1);

                    // Aggiungi righe per questa freccia usando la HikingRoute a cui appartiene l'arrow
                    $this->addArrowRows($direction, $rows, $arrowHikingRoute, $pole, $routeSignage, $ldpN, $tabN);
                }
            }
        }

        // Carica le coordinate dei poles dopo aver raccolto tutti gli ID
        $allPoleIds = $this->collectAllPoleIds();
        $this->loadPoleCoordinates($allPoleIds);
    }

    /**
     * Ordina i poles secondo points_order
     */
    protected function orderPolesByPointsOrder(Collection $poles, ?array $pointsOrder): array
    {
        if (! is_array($pointsOrder) || empty($pointsOrder)) {
            // Se non c'è points_order, restituisci i poles nell'ordine originale
            return $poles->all();
        }

        // Crea una mappa pole_id -> pole
        $poleMap = [];
        foreach ($poles as $pole) {
            $poleMap[(string) $pole->id] = $pole;
        }

        // Ordina secondo points_order
        $orderedPoles = [];
        foreach ($pointsOrder as $poleId) {
            $poleIdStr = (string) $poleId;
            if (isset($poleMap[$poleIdStr])) {
                $orderedPoles[] = $poleMap[$poleIdStr];
                unset($poleMap[$poleIdStr]);
            }
        }

        // Aggiungi eventuali poles non presenti in points_order alla fine
        foreach ($poleMap as $pole) {
            $orderedPoles[] = $pole;
        }

        return $orderedPoles;
    }

    /**
     * Raccoglie tutti gli ID dei Poles da tutte le HikingRoute
     */
    protected function collectAllPoleIds(): array
    {
        $poleIds = [];

        foreach ($this->expandedData as $data) {
            $pole = $data['pole'] ?? null;
            if ($pole && isset($pole->id)) {
                $poleIds[] = $pole->id;
            }
        }

        return array_unique($poleIds);
    }

    /**
     * Aggiunge le 2 righe per una freccia (forward o backward)
     */
    protected function addArrowRows(
        string $direction,
        array $rows,
        HikingRoute $hikingRoute,
        Poles $pole,
        array $routeSignage,
        string $ldpN,
        string $tabN
    ): void {
        if (empty($rows)) {
            return;
        }

        // Prima riga (first): Meta/Ore
        $this->expandedData[] = [
            'direction' => $direction,
            'row_type' => 'first',
            'hiking_route' => $hikingRoute,
            'pole' => $pole,
            'route_signage' => $routeSignage,
            'destinations' => $rows,
            'ldp_n' => $ldpN,
            'tab_n' => $tabN,
        ];

        // Seconda riga (second): Info/Km
        $this->expandedData[] = [
            'direction' => $direction,
            'row_type' => 'second',
            'hiking_route' => $hikingRoute,
            'pole' => $pole,
            'route_signage' => $routeSignage,
            'destinations' => $rows,
            'ldp_n' => $ldpN,
            'tab_n' => $tabN,
        ];
    }

    /**
     * Cache dei dati della HikingRoute per evitare ricalcoli
     */
    protected function cacheHikingRouteData(HikingRoute $hikingRoute): void
    {
        if (isset($this->hikingRouteDataCache[$hikingRoute->id])) {
            return;
        }

        $hikingRouteProperties = $hikingRoute->properties ?? [];
        $osmfeaturesData = $this->parseOsmfeaturesData($hikingRoute->osmfeatures_data);

        $firstClub = $hikingRoute->clubs->first();
        $firstArea = $hikingRoute->areas->first();

        $this->hikingRouteDataCache[$hikingRoute->id] = [
            'ref' => $hikingRouteProperties['ref'] ?? ($osmfeaturesData['properties']['ref'] ?? null),
            'ref_rei' => $hikingRoute->getRefReiAttribute(),
            'club_name' => $firstClub ? $firstClub->name : '',
            'area_name' => $firstArea ? $firstArea->name : '',
            'points_order' => $hikingRouteProperties['dem']['points_order'] ?? null,
        ];
    }

    /**
     * Parse osmfeatures_data in array
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
     * Formatta ldpN come "001.00", "002.00", ecc.
     */
    protected function formatLdpN(int $index): string
    {
        return str_pad($index, 3, '0', STR_PAD_LEFT) . '.00';
    }

    /**
     * Carica le coordinate dei poles dalla geometry in una singola query
     */
    protected function loadPoleCoordinates(array $poleIds): void
    {
        if (empty($poleIds)) {
            return;
        }

        $results = DB::table('poles')
            ->whereIn('id', $poleIds)
            ->whereNotNull('geometry')
            ->select('id', DB::raw('ST_Y(geometry::geometry) as latitude'), DB::raw('ST_X(geometry::geometry) as longitude'))
            ->get();

        foreach ($results as $result) {
            if ($result->latitude !== null && $result->longitude !== null) {
                $this->poleCoordinates[$result->id] = [
                    'latitude' => round((float) $result->latitude, 4),
                    'longitude' => round((float) $result->longitude, 4),
                ];
            }
        }
    }

    /**
     * Get the collection of data to export
     * Include le 2 righe di intestazione come prime righe
     */
    public function collection(): Collection
    {
        // Aggiungi le 2 righe di intestazione all'inizio
        $data = [
            ['_header_row' => 1],
            ['_header_row' => 2],
        ];

        // Aggiungi i dati espansi
        $data = array_merge($data, $this->expandedData);

        return collect($data);
    }

    /**
     * Get the headings for the export
     * Restituiamo array vuoto perché gestiamo le intestazioni manualmente nella collection
     */
    public function headings(): array
    {
        return [];
    }

    /**
     * Map each row of data
     */
    public function map($row): array
    {
        // Gestisci le righe di intestazione
        if (isset($row['_header_row'])) {
            return $row['_header_row'] === 1 ? self::HEADER_ROW_1 : self::HEADER_ROW_2;
        }

        return $this->mapDataRow($row);
    }

    /**
     * Mappa una riga di dati
     */
    protected function mapDataRow(array $row): array
    {
        $hikingRoute = $row['hiking_route'];
        $pole = $row['pole'];
        $destinations = $row['destinations'];
        $rowType = $row['row_type'];
        $direction = $row['direction'] ?? 'forward';
        $ldpN = $row['ldp_n'] ?? null;
        $tabN = $row['tab_n'] ?? null;

        // Recupera dati dalla cache
        $hrData = $this->hikingRouteDataCache[$hikingRoute->id] ?? $this->getHikingRouteData($hikingRoute);
        $coordinates = $this->poleCoordinates[$pole->id] ?? ['latitude' => null, 'longitude' => null];

        // Prepara i codici
        $ldpNForCode = str_replace('.', '-', $ldpN);
        $codiceLdp = $this->buildCodiceLdp($hrData['area_name'], $hrData['ref_rei'], $ldpNForCode);
        $codiceTabella = $this->buildCodiceTabella($hrData['ref_rei'], $ldpNForCode, $tabN);

        if ($rowType === 'first') {
            return $this->buildFirstRow($hrData, $ldpN, $tabN, $direction, $destinations, $codiceLdp, $pole);
        }

        return $this->buildSecondRow($destinations, $coordinates, $codiceTabella, $pole);
    }

    /**
     * Recupera i dati della HikingRoute (fallback se non in cache)
     */
    protected function getHikingRouteData(HikingRoute $hikingRoute): array
    {
        $this->cacheHikingRouteData($hikingRoute);

        return $this->hikingRouteDataCache[$hikingRoute->id];
    }

    /**
     * Costruisce il Codice Ldp
     */
    protected function buildCodiceLdp(string $areaName, string $refRei, string $ldpNForCode): string
    {
        return $areaName . '-' . str_replace('.', '', $refRei) . '-' . $ldpNForCode;
    }

    /**
     * Costruisce il Codice Tabella
     */
    protected function buildCodiceTabella(string $refRei, string $ldpNForCode, ?string $tabN): string
    {
        $codice = str_replace('.', '', $refRei);
        if ($ldpNForCode !== null) {
            $codice .= '-' . $ldpNForCode;
        }
        if ($tabN !== null) {
            $codice .= '-' . $tabN;
        }

        return $codice;
    }

    /**
     * Recupera il name da destination o dal pole
     */
    protected function getName(Poles $pole, array $destination = []): string
    {
        // Prima cerca nel destination (se presente)
        if (! empty($destination['name'])) {
            return $destination['name'];
        }

        // Altrimenti cerca nel name del pole
        return  $pole->name ?? '';
    }

    /**
     * Recupera il description da destination o dal pole
     */
    protected function getDescription(Poles $pole, array $destination = []): string
    {
        // Prima cerca nel destination (se presente)
        if (! empty($destination['description'])) {
            return $destination['description'];
        }

        // Altrimenti cerca nelle properties del pole
        $poleProperties = $pole->properties ?? [];

        return $poleProperties['description'] ?? '';
    }

    /**
     * Costruisce la prima riga (Meta/Ore)
     */
    protected function buildFirstRow(array $hrData, ?string $ldpN, ?string $tabN, string $direction, array $destinations, string $codiceLdp, Poles $pole): array
    {
        $dir = $direction === 'forward' ? 'D' : 'S';
        $poleHyperlink = $this->createPoleHyperlink($pole);

        return [
            $poleHyperlink, // ID Pole (link cliccabile)
            $hrData['club_name'], // Soggetto manutentore
            $hrData['ref_rei'], // N. Sent.
            $ldpN, // Ldp n.
            $tabN, // tab n.
            $hrData['ref'], // Sentiero
            '', // Logo lungo it
            $this->getName($pole, $destinations[0] ?? []), // Meta 1
            $this->formatTime($destinations[0]['time_hiking'] ?? null), // Ore 1
            $this->getName($pole, $destinations[1] ?? []), // Meta 2
            $this->formatTime($destinations[1]['time_hiking'] ?? null), // Ore 2
            $this->getName($pole, $destinations[2] ?? []), // Meta 3
            $this->formatTime($destinations[2]['time_hiking'] ?? null), // Ore 3
            $dir, // Dir.
            '', // Località 1
            '', // Località 2
            '', // Quota
            $codiceLdp, // Codice Ldp
        ];
    }

    /**
     * Costruisce la seconda riga (Info/Km)
     */
    protected function buildSecondRow(array $destinations, array $coordinates, string $codiceTabella, Poles $pole): array
    {
        return [
            '', // ID Pole (vuoto nella seconda riga)
            '', // Soggetto finanziatore
            '', // Logo 1 meta 1
            '', // Logo 2 meta 1
            '', // Logo 1 meta 2
            '', // Logo 2 meta 2
            '', // Logo 1 meta 3
            $this->getDescription($pole, $destinations[0] ?? []), // Info meta 1
            $this->formatDistance($destinations[0]['distance'] ?? null), // Km 1
            $this->getDescription($pole, $destinations[1] ?? []), // Info meta 2
            $this->formatDistance($destinations[1]['distance'] ?? null), // Km 2
            $this->getDescription($pole, $destinations[2] ?? []), // Info meta 3
            $this->formatDistance($destinations[2]['distance'] ?? null), // Km 3
            '', // Logo 2 meta 3
            $coordinates['latitude'], // Latutudine
            $coordinates['longitude'], // Longitudine
            '', // QR code
            $codiceTabella, // Codice Tabella
        ];
    }

    /**
     * Format time_hiking in formato "h H:M"
     */
    protected function formatTime(?int $minutes): string
    {
        if ($minutes === null || $minutes === '') {
            return '';
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        return 'h ' . $hours . ':' . str_pad($mins, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Format distance in km con una cifra decimale
     */
    protected function formatDistance(?float $distance): string
    {
        if ($distance === null || $distance === '') {
            return '';
        }

        // Converti da metri a km e arrotonda alla prima cifra decimale
        $distanceKm = round($distance / 1000, 1);

        return 'km ' . $distanceKm;
    }

    /**
     * Crea la formula HYPERLINK per Excel che mostra l'ID del Pole come link cliccabile
     */
    protected function createPoleHyperlink(Poles $pole): string
    {
        $poleUrl = url('/resources/' . PolesResource::uriKey() . '/' . $pole->id);
        $poleId = $pole->id;

        return '=HYPERLINK("' . $poleUrl . '", "' . $poleId . '")';
    }

    /**
     * Apply styles to the worksheet
     */
    public function styles(Worksheet $sheet)
    {
        $styles = [];

        // Stile per le 2 righe di intestazione (righe 1 e 2)
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

        $styles[1] = $headerStyle; // Prima riga intestazione
        $styles[2] = $headerStyle; // Seconda riga intestazione

        // Applica stile alternato alle righe dati per distinguere le frecce
        $rowIndex = 3; // Inizia dalla riga 3 (dopo le intestazioni)

        foreach ($this->expandedData as $data) {
            $isFirstRow = $data['row_type'] === 'first';

            $styles[$rowIndex] = [
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $isFirstRow ? 'FFFFFF' : 'F5F5F5'], // Bianco o grigio chiaro
                ],
            ];

            // Applica colore blu solo alla colonna A (Palo) nelle righe "first"
            if ($isFirstRow) {
                $cellCoordinate = 'A' . $rowIndex; // Colonna A
                $styles[$cellCoordinate] = [
                    'font' => [
                        'color' => ['rgb' => '0000FF'], // Blu classico per i link
                    ],
                ];
            }

            $rowIndex++;
        }

        return $styles;
    }
}
