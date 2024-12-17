<?php

namespace App\Nova\Cards;

use Abordage\HtmlCard\HtmlCard;

class Osm2caiStatusCard extends HtmlCard
{
    private $osm2caiStatus;

    public function __construct(string|int|null $osm2caiStatus)
    {
        $this->osm2caiStatus = $osm2caiStatus;
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
        $status = $this->osm2caiStatus ?? '';

        return <<<HTML
      <h1 class='text-4xl'>$status</h1><h3 class='text-lg text-gray-400'>Stato di Accatastamento</h3> <p text-gray-200>Data di validazione : TBI </p><p text-gray-200>Validatore : TBI </p>
      HTML;
    }
}
