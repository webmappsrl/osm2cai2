<?php

namespace App\Traits;

use App\Models\Club;
use App\Models\Section;
use App\Models\User;

trait CsvableModelTrait
{
    public function getCsv(): string
    {
        $line = 'sda,settore,ref,source ref,from,to,difficoltà,codice rei,codice rei osm,osm,osm2cai,percorribilitá,ultimo aggiornamento percorribilitá,ultimo aggiornamento effettuato da:, codice sezione, nome sezione ' . PHP_EOL;
        if (count($this->hikingRoutes->whereIn('osm2cai_status', [1, 2, 3, 4]))) {
            foreach ($this->hikingRoutes->whereIn('osm2cai_status', [1, 2, 3, 4]) as $hr) {
                $user = User::find($hr->issues_user_id);
                $osmfeaturesDataProperties = $hr->osmfeatures_data['properties'];
                $sectionName = Club::wherecaiCode($osmfeaturesDataProperties['source_ref'])->first()->name ?? '';

                $line .= $hr->osm2cai_status . ',';
                $line .= ($hr->mainSector()->full_code ?? '')  . ',';
                $line .= $osmfeaturesDataProperties['ref'] . ',';
                $line .= $osmfeaturesDataProperties['source_ref'] . ',';
                $line .= $osmfeaturesDataProperties['from'] . ',';
                $line .= $osmfeaturesDataProperties['to'] . ',';
                $line .= $osmfeaturesDataProperties['cai_scale'] . ',';
                $line .= $osmfeaturesDataProperties['ref_REI'] . ',';
                $line .= $osmfeaturesDataProperties['osm_id'] . ',';
                $line .= url('/resources/hiking-routes/' . $hr->id) . ',';
                $line .= $hr->issues_status . ',';
                $line .= $hr->issues_last_update . ',';
                $line .= $user->name ?? '' . ',';
                $line .= $osmfeaturesDataProperties['source_ref'] ?? '' . ',';
                $line .= ',';
                $line .= $sectionName ?? '';
                $line .= PHP_EOL;
            }
        }
        return $line;
    }
}
