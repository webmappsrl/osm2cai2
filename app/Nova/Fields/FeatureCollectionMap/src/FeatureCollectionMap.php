<?php

namespace App\Nova\Fields\FeatureCollectionMap\src;

use Laravel\Nova\Fields\Text;

class FeatureCollectionMap extends Text
{

    /**
     * Create a new field.
     *
     * @param  string  $name
     * @param  string|callable|null  $attribute
     * @param  callable|null  $resolveCallback
     * @return void
     */
    public function __construct($name, $attribute = null, callable $resolveCallback = null)
    {
        parent::__construct($name, $attribute, $resolveCallback);
        
        // Imposta automaticamente il rendering come HTML
        $this->asHtml();
        
        // Imposta automaticamente soloOnDetail
        $this->onlyOnDetail();
        
        // Imposta il callback di default per generare la mappa
        $this->resolveUsing(function ($value, $resource) {
            \Log::info('FeatureCollectionMap resolve called for resource ID: ' . $resource->id);
            return $this->generateMapHtml($resource);
        })->asHtml();
    }

    /**
     * Genera l'HTML per la mappa
     *
     * @param  mixed  $resource
     * @return string
     */
    protected function generateMapHtml($resource)
    {
        return <<<HTML
            <div style="min-height: 400px; position: relative;background: white;">
                <iframe 
                    src="/nova-vendor/feature-collection-map/widget?geojson={$resource->id}"
                    style="width: 100%; height: 500px; border: none; border-radius: 4px;"
                    frameborder="0"
                    allowfullscreen>
                </iframe>
            </div>
        HTML;
    }

    /**
     * Permette di personalizzare l'URL del GeoJSON
     *
     * @param  string|callable  $url
     * @return $this
     */
    public function geojsonUrl($url)
    {
        $this->resolveUsing(function ($value, $resource) use ($url) {
            $geojsonUrl = is_callable($url) ? $url($resource) : $url;
            return $this->generateMapHtmlWithUrl($resource, $geojsonUrl);
        })->asHtml();

        return $this;
    }

    /**
     * Permette di personalizzare l'altezza dell'iframe
     *
     * @param  int  $height
     * @return $this
     */
    public function height($height = 500)
    {
        $this->resolveUsing(function ($value, $resource) use ($height) {
            return $this->generateMapHtmlWithUrl($resource, $resource->id, $height);
        })->asHtml();

        return $this;
    }

    /**
     * Genera l'HTML per la mappa con URL e altezza personalizzati
     *
     * @param  mixed  $resource
     * @param  string  $geojsonUrl
     * @param  int  $height
     * @return string
     */
    protected function generateMapHtmlWithUrl($resource, $geojsonUrl, $height = 500)
    {
        // Se geojsonUrl è un URL completo, estraiamo solo l'ID
        if (filter_var($geojsonUrl, FILTER_VALIDATE_URL)) {
            $geojsonUrl = basename(parse_url($geojsonUrl, PHP_URL_PATH));
        }
        
        return <<<HTML
            <div style="min-height: 400px; position: relative;background: white;">
                <iframe 
                    src="{$geojsonUrl}"
                    style="width: 100%; height: {$height}px; border: none; border-radius: 4px;"
                    frameborder="0"
                    allowfullscreen>
                </iframe>
            </div>
        HTML;
    }
}
