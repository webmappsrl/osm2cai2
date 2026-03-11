<?php

namespace App\Enums;

enum SicaiSituazioneEnum: string
{
    case HaAderito = 'ha aderito';
    case NonHaAderito = 'non ha aderito';
    case InLavorazione = 'in lavorazione';
    case DaContattare = 'da contattare';
    case Contattato = 'contattato';
}
