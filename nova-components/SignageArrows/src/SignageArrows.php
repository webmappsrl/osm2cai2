<?php

namespace Osm2cai\SignageArrows;

use Laravel\Nova\Fields\Field;

/**
 * SignageArrows - Campo Nova per la visualizzazione delle frecce segnaletica CAI
 *
 * Visualizza le frecce forward (destra) e backward (sinistra) con i dati
 * della segnaletica estratti da properties->signage
 */
class SignageArrows extends Field
{
    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'signage-arrows';

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

        $this->onlyOnDetail();
    }

    /**
     * Resolve the field's value for display.
     *
     * @param  mixed  $resource
     */
    protected function resolveAttribute($resource, string $attribute): mixed
    {
        // Ottieni le properties dal model
        $properties = $resource->properties ?? [];

        // Estrai i dati signage
        $signage = $properties['signage'] ?? [];

        return $signage;
    }
}
