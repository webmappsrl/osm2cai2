<?php

namespace Wm\Osm2caiMapMultiLinestring;

use Laravel\Nova\Fields\Field;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Http\Requests\NovaRequest;

class Osm2caiMapMultiLinestring extends Field
{
    /**
     * The field's component.
     *
     * @var string
     */
    public $component = 'osm2cai-map-multi-linestring';
}
