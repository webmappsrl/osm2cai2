<?php

namespace App\Nova;

class GeologicalSite extends AbstractValidationResource
{
    public static function getFormId(): string
    {
        return 'geological_site';
    }

    public static function getLabel(): string
    {
        return __('Geological Sites');
    }
}
