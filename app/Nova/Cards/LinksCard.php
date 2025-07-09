<?php

namespace App\Nova\Cards;

use Abordage\HtmlCard\HtmlCard;

class LinksCard extends HtmlCard
{
    private $linksData;

    public function __construct(array $linksData)
    {
        $this->linksData = $linksData;
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
    public bool $center = true;

    /**
     * Html content
     */
    public function content(): string
    {
        return <<<HTML
            <p>OpenStreetMap: <a style="color:blue" target="_blank" href="{$this->linksData['openstreetmapLink']}">{$this->linksData['osm_id']}</a></p>
            <p>Waymarkedtrails: <a style="color:blue" target="_blank" href="{$this->linksData['waymarkedtrailsLink']}">{$this->linksData['osm_id']}</a></p>
            <p>OSM Relation Analyzer: <a style="color:blue" target="_blank" href="{$this->linksData['analyzerLink']}">{$this->linksData['osm_id']}</a></p>
            <p>OSM2CAI: <a style="color:blue" target="_blank" href="{$this->linksData['osm2caiLink']}">{$this->linksData['id']}</a></p>
            <p>INFOMONT: <a style="color:blue" target="_blank" href="{$this->linksData['infomontLink']}">{$this->linksData['id']}</a></p>
            HTML;
    }
}
