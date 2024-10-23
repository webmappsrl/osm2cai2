<?php

namespace App\Nova;

class ArchaeologicalSite extends AbstractValidationResource
{
    public static function getFormId(): string
    {
        return 'archaeological_site';
    }

    public static function getLabel(): string
    {
        return 'Siti Archeologici';
    }
}
