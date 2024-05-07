<?php

namespace App\Helpers;

class Osm2caiHelper
{
    /**
     * Get the openstreetmap url by parsing the osmfeatures id
     * @param string $id
     * @return string
     */
    public static function getOpenstreetmapUrl(string $id): string
    {
        $osmtype = substr($id, 0, 1);

        $finalType = match ($osmtype) {
            'N' => 'node',
            'W' => 'way',
            'R' => 'relation',
        };
        $osmid = substr($id, 1);

        return 'https://www.openstreetmap.org/'.$finalType.'/'.$osmid;
    }

    /**
     * Get the openstreetmap url as html string
     * @param string $id
     * @return string
     */
    public static function getOpenstreetmapUrlAsHtml(string $id): string
    {
        return '<a style="color:darkgreen;" target="_blank" href="'.self::getOpenstreetmapUrl($id).'" target="_blank">'.$id.'</a>';
    }

    /**
     * Display the score as stars
     * @param int $score
     * @return string
     */
    public static function getScoreAsStars(int $score): string
    {
        $stars = '';

        if ($score == 0 || $score == null) {
            return '0';
        }
        for ($i = 0; $i < $score; $i++) {
            $stars .= '⭐';
        }

        return $stars;
    }
}
