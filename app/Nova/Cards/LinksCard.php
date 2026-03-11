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
        $osmHtml = '';
        if (! empty($this->linksData['osm_id'])) {
            $osmId = $this->linksData['osm_id'];
            $osmHtml = <<<HTML
            <p>OpenStreetMap: <a style="color:blue" target="_blank" href="{$this->linksData['openstreetmapLink']}">{$osmId}</a></p>
            <p>Waymarkedtrails: <a style="color:blue" target="_blank" href="{$this->linksData['waymarkedtrailsLink']}">{$osmId}</a></p>
            <p>OSM Relation Analyzer: <a style="color:blue" target="_blank" href="{$this->linksData['analyzerLink']}">{$osmId}</a></p>
            HTML;
        }

        $webappHtml = '';
        if (! empty($this->linksData['webappLink'])) {
            $webappHtml = "<p>Webapp: <a style=\"color:blue\" target=\"_blank\" href=\"{$this->linksData['webappLink']}\">{$this->linksData['id']}</a></p>";
        }

        return <<<HTML
            {$osmHtml}
            {$webappHtml}
            <p>Hiking Route JSON (GeoJSON): <a style="color:blue" target="_blank" href="{$this->linksData['geojsonApiLink']}">Download JSON</a></p>
            <p>Hiking Route GPX: <a style="color:blue" target="_blank" href="{$this->linksData['gpxApiLink']}">Download GPX</a></p>
            HTML;
    }
}
