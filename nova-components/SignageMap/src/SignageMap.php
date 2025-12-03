<?php

namespace Wm\SignageMap;

use Laravel\Nova\Fields\Field;

class SignageMap extends Field
{
    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'signage-map';

    /**
     * Create a new field.
     *
     * @param  string  $name
     * @param  string|callable|null  $attribute
     * @return void
     */
    public function __construct($name, $attribute = null, ?callable $resolveCallback = null)
    {
        parent::__construct($name, $attribute, $resolveCallback);

        // Imposta automaticamente soloOnDetail
        $this->onlyOnDetail();
    }

    /**
     * Permette di personalizzare l'URL del GeoJSON
     *
     * @param  string|callable  $url
     * @return $this
     */
    public function geojsonUrl($url)
    {
        return $this->withMeta(['geojsonUrl' => $url]);
    }

    /**
     * Permette di personalizzare l'altezza della mappa
     *
     * @param  int  $height
     * @return $this
     */
    public function height(int $height = 500)
    {
        return $this->withMeta(['height' => $height]);
    }

}




