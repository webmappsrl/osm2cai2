<?php

return [
    /**
     * Minimum distance from first and last point used to identify automatically roundtrip hiking routes
     */
    'roundtrip_threshold' => env('OSM2CAI_ROUNDTRIP_THRASHOLD', 100),

    'region_istat_name' => [
        '1' => 'Piemonte',
        '2' => "Valle d'Aosta",
        '3' => 'Lombardia',
        '4' => 'Trentino Alto Adige',
        '5' => 'Veneto',
        '6' => 'Friuli Venezia Giulia',
        '7' => 'Liguria',
        '8' => 'Emilia Romagna',
        '9' => 'Toscana',
        '10' => 'Umbria',
        '11' => 'Marche',
        '12' => 'Lazio',
        '13' => 'Abruzzo',
        '14' => 'Molise',
        '15' => 'Campania',
        '16' => 'Puglia',
        '17' => 'Basilicata',
        '18' => 'Calabria',
        '19' => 'Sicilia',
        '20' => 'Sardegna',
    ],

    'regions_code' => [
        'Friuli-Venezia Giulia' => 'A',
        'Veneto' => 'B',
        'Trentino-Alto Adige/Südtirol' => 'C',
        'Lombardia' => 'D',
        'Piemonte' => 'E',
        "Valle d'Aosta / Vallée d'Aoste" => 'F',
        'Liguria' => 'G',
        'Emilia-Romagna' => 'H',
        'Toscana' => 'L',
        'Umbria' => 'N',
        'Marche' => 'M',
        'Lazio' => 'O',
        'Abruzzo' => 'P',
        'Molise' => 'Q',
        'Campania' => 'S',
        'Puglia' => 'R',
        'Basilicata' => 'T',
        'Calabria' => 'U',
        'Sicilia' => 'V',
        'Sardigna/Sardegna' => 'Z',
    ],

    'hiking_route_buffer' => 250,

    'nominatim' => [
        'host' => env('NOMINATIM_HOST', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('NOMINATIM_USER_AGENT', 'OSM2CAI/1.0 (https://osm2cai.cai.it)'),
    ],

    'osmTagsMapping' => [
        'amenity' => [
            'monastery' => 'monastery',
            'castle' => 'castle',
            'place_of_worship' => 'place_of_worship',
            'ruins' => 'ruins',
            'museum' => 'museum',
            'theatre' => 'theatre',
        ],
        'historic' => [
            'castle' => 'castle',
            'archeological_site' => 'archeological_site',
            'tower' => 'tower',
            'city_gate' => 'city_gate',
            'ruins' => 'ruins',
            'museum' => 'museum',
        ],
        'building' => [
            'castle' => 'castle',
            'monastery' => 'monastery',
            'ruins' => 'ruins',
            'tower' => 'tower',
            'museum' => 'museum',
        ],
        'religion' => [
            'christian' => 'christian',
        ],
        'man_made' => [
            'tower' => 'tower',
        ],
        'tourism' => [
            'museum' => 'museum',
            'theatre' => 'theatre',
        ],
        'natural' => [
            'cave_entrance' => 'cave_entrance',
            'peak' => 'peak',
            'saddle' => 'saddle',
            'volcano' => 'volcano',
            'cliff' => 'cliff',
            'water' => 'water',
            'hot_spring' => 'hot_spring',
            'spring' => 'spring',
        ],
        'water' => [
            'lake' => 'lake',
            'river' => 'river',
            'waterfall' => 'waterfall',
        ],
    ],
];
