<?php

namespace App\Nova\Cards;

use Abordage\HtmlCard\HtmlCard;
use App\Models\HikingRoute;

class RefCard extends HtmlCard
{
    private $osmTags;

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
        $refRei = $osmfeaturesDataProperties['ref:REI'] ?? '/';
        $sectors = $this->hr->sectors()->pluck('name')->implode(', ');

        return <<<HTML
       <h1 class='text-4xl'>REF:$ref (CODICE REI: $refRei)</h1><p class='text-lg text-gray-400 text-center'>Settori: $sectors</p> 
       HTML;
    }
}
