<?php

namespace App\Traits;

use App\Helpers\Osm2caiHelper;
use App\Models\Club;
use App\Models\HikingRoute;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Collection;

trait CsvableModelTrait
{
    public function getCsv(): string
    {
        // Headers definition
        $headers = [
            'sda',
            'settore',
            'ref',
            'source ref',
            'from',
            'to',
            'difficoltà',
            'codice rei',
            'osm',
            'osm2cai',
            'percorribilità',
            'ultimo aggiornamento percorribilità',
            'ultimo aggiornamento effettuato da',
            'codice sezione',
            'nome sezione',
        ];

        // Start with headers
        $csvLines = [implode(',', $headers)];

        // Optimized Query: Filter in DB, Eager Load relationships
        $hikingRoutes = $this->hikingRoutes()
            ->whereIn('osm2cai_status', [1, 2, 3, 4])
            ->with(['issueUser:id,name', 'sectors']) // Load sectors for mainSector logic if needed
            ->select([
                'hiking_routes.id',
                'osm2cai_status',
                'issues_user_id',
                'osmfeatures_id',
                'osmfeatures_data',
                'issues_status',
                'issues_last_update',
            ])
            ->get();

        if ($hikingRoutes->isNotEmpty()) {
            $sourceRefs = $hikingRoutes->map(function ($hr) {
                return $hr->osmfeatures_data['properties']['source_ref'] ?? null;
            })->filter()->unique()->values();

            $clubMap = collect(); // Default to empty collection
            if ($sourceRefs->isNotEmpty()) {
                $clubMap = Club::whereIn('cai_code', $sourceRefs)->pluck('name', 'cai_code');
            }

            foreach ($hikingRoutes as $hr) {
                $user = $hr->issueUser; // Access the loaded relationship
                $osmfeaturesDataProperties = $hr->osmfeatures_data['properties'] ?? [];

                $sourceRef = $osmfeaturesDataProperties['source_ref'] ?? null;
                $sectionName = $sourceRef ? ($clubMap[$sourceRef] ?? '') : '';

                $mainSectorCode = $hr->mainSector()->full_code ?? '';

                $data = [
                    $hr->osm2cai_status,
                    $mainSectorCode,
                    $osmfeaturesDataProperties['ref'] ?? '',
                    $sourceRef ?? '',
                    $osmfeaturesDataProperties['from'] ?? '',
                    $osmfeaturesDataProperties['to'] ?? '',
                    $osmfeaturesDataProperties['cai_scale'] ?? '',
                    $osmfeaturesDataProperties['ref_REI'] ?? '',
                    Osm2caiHelper::getOpenstreetmapUrl($hr->osmfeatures_id ?? null),
                    url('/resources/hiking-routes/'.$hr->id),
                    $hr->issues_status,
                    $hr->issues_last_update,
                    $user->name ?? '',
                    $sourceRef ?? '',
                    $sectionName,
                ];

                $csvLines[] = implode(',', array_map(function ($value) {
                    $value = str_replace('"', '""', $value ?? '');

                    return '"'.$value.'"';
                }, $data));
            }
        }

        return implode(PHP_EOL, $csvLines); // Join lines at the end
    }
}
