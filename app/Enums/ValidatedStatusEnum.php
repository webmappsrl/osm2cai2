<?php

namespace App\Enums;

enum ValidatedStatusEnum: string
{
    case VALID = 'valid';
    case INVALID = 'invalid';
    case NOT_VALIDATED = 'not_validated';
}
