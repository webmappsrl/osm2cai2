<?php

namespace App\Nova\Cards;

use Abordage\HtmlCard\HtmlCard;
use App\Models\HikingRoute;

class RefCard extends HtmlCard
{
    public function __construct(HikingRoute $hr)
    {
        $this->hr = $hr;
        parent::__construct();
    }

    /**
     * The width of the card (1/2, 1/3, 1/4 or full).
     */
    public $width = '1/2';

    /**
     * The height strategy of the card (fixed or dynamic).
     */
    public $height = 'fixed';

    /**
     * Align content to the center of the card.
     */
    public bool $center = true;

    /**
     * Html content
     */
    public function content(): string
    {
        if (empty($this->hr->osmfeatures_data) || is_null($this->hr->osmfeatures_data)) {
            return 'No data';
        }

        $osmfeaturesDataProperties = is_string($this->hr->osmfeatures_data) ? json_decode($this->hr->osmfeatures_data, true) : $this->hr->osmfeatures_data['properties'];

        $ref = $osmfeaturesDataProperties['ref'] ?? '/';
        $refRei = $osmfeaturesDataProperties['ref_REI'] ?? '/';
        $refReiComp = $this->hr->ref_rei_comp;
        $sectors = $this->hr->sectors()->pluck('name')->implode(', ');

        return <<<HTML
            <h1 class='text-3xl'>REF:$ref (CODICE REI: $refRei/$refReiComp)</h1><p class='text-lg text-gray-400 text-center'>Settori: {$this->getSectorsString($this->hr)}</p> 
            HTML;
    }

    public function getSectorsString(HikingRoute $hr): string
    {
        $s = 'ND';
        if (count($hr->sectors) > 0) {
            $sectors = [];
            foreach ($hr->sectors as $sector) {
                $sectors[] = $sector->full_code.'('.number_format($sector->pivot->percentage, 2).'%)';
            }
            $s = implode('; ', $sectors);
        }

        return $s;
    }
}
