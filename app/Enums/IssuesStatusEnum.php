<?php

namespace App\Enums;

enum IssuesStatusEnum: string
{
    case Unknown = 'sconosciuto';
    case Open = 'percorribile';
    case Closed = 'non percorribile';
    case PartiallyClosed = 'percorribile parzialmente';
}
