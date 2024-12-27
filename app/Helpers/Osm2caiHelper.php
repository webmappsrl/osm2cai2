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
        $openstreetmapUrl = self::getOpenstreetmapUrl($id);

        return <<<HTML
        <a style="color:darkgreen;" target="_blank" href="{$openstreetmapUrl}" target="_blank">{$id}</a>
        HTML;
    }

    /**
     * Display the score as stars
     * @param int $score
     * @return string
     */
    public static function getScoreAsStars(?int $score): string
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

    /**
     * Get the osmfeatures data correctly formatted as array without the geometry
     * @param string $data
     * @return array
     */
    public static function getOsmfeaturesDataForNovaDetail(string $data): string
    {
        return cache()->remember('osmfeatures_data_'.md5($data), 60 * 60 * 24, function () use ($data) {
            $data = json_decode($data, true);

            if (! is_array($data)) {
                $data = json_decode($data, true);
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                return '';
            }

            $dataOrdered = [
                'type' => $data['type'] ?? '',
                'properties' => $data['properties'] ?? [],
                'geometry' => $data['geometry'] ?? [],
            ];

            return json_encode($dataOrdered, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        });
    }

    /**
     * It returns RGB string for SAL color according to the following rules
     * SAL <= 0.2 -> #f1eef6
     * 0.2 < SAL <= 0.4 -> #bdc9e1
     * 0.4 < SAL <= 0.6 -> #74a9cf
     * 0.6 < SAL <= 0.8 -> #2b8cbe
     * 0.8 < SAL  -> #045a8d
     *
     * @param float $sal
     * @return string
     */
    public static function getSalColor(float $sal): string
    {
        $color = '';
        if ($sal <= 0.2) {
            $color = '#f1eef6';
        } elseif ($sal <= 0.4) {
            $color = '#bdc9e1';
        } elseif ($sal <= 0.6) {
            $color = '#74a9cf';
        } elseif ($sal <= 0.8) {
            $color = '#2b8cbe';
        } else {
            $color = '#045a8d';
        }

        return $color;
    }

    /**
     * 0 969696
     * 1 FFD23F
     * 2 B43E8F
     * 3 1E3888
     * 4 47AC34
     *
     * @param $sda
     * @return string
     */
    public static function getSdaColor($sda): string
    {
        $color = '';
        switch ($sda) {
            case '0':
                $color = '#969696';
                break;
            case '1':
                $color = '#FFD23F';
                break;
            case '2':
                $color = '#B43E8F';
                break;
            case '3':
                $color = '#1E3888';
                break;
            case '4':
                $color = '#47AC34';
                break;
        }

        return $color;
    }
}
