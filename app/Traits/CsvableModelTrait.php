<?php

namespace App\Traits;

use App\Models\Club;
use App\Models\User;
use App\Models\Section;
use App\Helpers\Osm2caiHelper;

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
            'nome sezione'
        ];

        // Start with headers
        $line = implode(',', $headers) . PHP_EOL;

        if (count($this->hikingRoutes->whereIn('osm2cai_status', [1, 2, 3, 4]))) {
            foreach ($this->hikingRoutes->whereIn('osm2cai_status', [1, 2, 3, 4]) as $hr) {
                $user = User::find($hr->issues_user_id);
                $osmfeaturesDataProperties = $hr->osmfeatures_data['properties'];
                $sectionName = Club::wherecaiCode($osmfeaturesDataProperties['source_ref'])->first()->name ?? '';

                // Prepare data in array to maintain order
                $data = [
                    $hr->osm2cai_status,
                    $hr->mainSector()->full_code ?? '',
                    $osmfeaturesDataProperties['ref'] ?? '',
                    $osmfeaturesDataProperties['source_ref'] ?? '',
                    $osmfeaturesDataProperties['from'] ?? '',
                    $osmfeaturesDataProperties['to'] ?? '',
                    $osmfeaturesDataProperties['cai_scale'] ?? '',
                    $osmfeaturesDataProperties['ref_REI'] ?? '',
                    Osm2caiHelper::getOpenstreetmapUrl($hr->osmfeatures_id) ?? '',
                    url('/resources/hiking-routes/' . $hr->id),
                    $hr->issues_status,
                    $hr->issues_last_update,
                    $user->name ?? '',
                    $osmfeaturesDataProperties['source_ref'] ?? '',
                    $sectionName ?? ''
                ];

                // Add row with data
                $line .= implode(',', array_map(function ($value) {
                    // Handle commas in values
                    return '"' . str_replace('"', '""', $value) . '"';
                }, $data)) . PHP_EOL;
            }
        }

        return $line;
    }
}
