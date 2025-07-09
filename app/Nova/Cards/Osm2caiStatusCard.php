<?php

namespace App\Nova\Cards;

use Abordage\HtmlCard\HtmlCard;
use App\Models\HikingRoute;

class Osm2caiStatusCard extends HtmlCard
{
    private $hr;

    public function __construct(HikingRoute $hr)
    {
        $this->hr = $hr;
        parent::__construct();
    }

    /**
     * The width of the card (1/2, 1/3, 1/4 or full).
     */
    public $width = '1/4';

    /**
     * The height strategy of the card (fixed or dynamic).
     */
    public $height = 'fixed';

    /**
     * Align content to the center of the card.
     */
    public bool $center = false;

    /**
     * Html content
     */
    public function content(): string
    {
        $status = $this->hr->osm2cai_status ?? '';
        $validationDate = $this->hr->validation_date ?? '';
        $validator = $this->hr->validator->name ?? '';

        return <<<HTML
            <h1 class='text-4xl'>$status</h1><h3 class='text-lg text-gray-400'>Stato di Accatastamento</h3> <p text-gray-200>Data di validazione : $validationDate </p><p text-gray-200>Validatore : $validator </p>
            HTML;
    }
}
