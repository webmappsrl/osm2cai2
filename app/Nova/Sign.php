<?php

namespace App\Nova;

class Sign extends AbstractValidationResource
{
    public static function getFormId(): string
    {
        return 'signs';
    }

    public static function getLabel(): string
    {
        return __('Traces of human activity'); // Segni dell'uomo
    }

    public static function getAuthorizationMethod(): string
    {
        return 'is_signs_validator';
    }
}
