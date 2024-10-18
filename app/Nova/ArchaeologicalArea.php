<?php

namespace App\Nova;

class ArchaeologicalArea extends AbstractValidationResource
{
    public static function getFormId(): string
    {
        return 'archaeological_area';
    }

    public static function getLabel(): string
    {
        return 'Aree Archeologiche';
    }
}
