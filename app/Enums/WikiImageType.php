<?php

namespace App\Enums;

enum WikiImageType: string
{
    case WIKIPEDIA_IMAGES = 'wikipedia_images';
    case WIKIMEDIA_IMAGES = 'wikimedia_images';
    case WIKIDATA_IMAGES = 'wikidata_images';
}
