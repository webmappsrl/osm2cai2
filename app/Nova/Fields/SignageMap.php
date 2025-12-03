<?php

namespace App\Nova\Fields;

use Wm\WmPackage\Nova\Fields\FeatureCollectionMap\src\FeatureCollectionMap;

/**
 * SignageMap - Campo Nova per la visualizzazione della mappa segnaletica CAI
 * 
 * Estende FeatureCollectionMap con un componente Vue custom che:
 * - Usa la mappa di FeatureCollectionMap
 * - Ha un popup personalizzato per la segnaletica
 */
class SignageMap extends FeatureCollectionMap
{
    /**
     * The field's component.
     * Usa un componente Vue dedicato per la segnaletica
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
    }
}
