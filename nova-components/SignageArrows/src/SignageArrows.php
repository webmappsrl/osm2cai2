<?php

namespace Osm2cai\SignageArrows;

use Laravel\Nova\Fields\Field;

/**
 * SignageArrows - Campo Nova per la visualizzazione delle frecce segnaletica CAI
 *
 * Visualizza le frecce forward (destra) e backward (sinistra) con i dati
 * della segnaletica. Accetta attributi in dot-notation (es. "properties.signage")
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
        // Usa data_get per supportare dot-notation (es. "properties.signage")
        return data_get($resource, $attribute) ?? [];
    }
}
