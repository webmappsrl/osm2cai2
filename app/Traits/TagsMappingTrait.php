<?php

namespace App\Traits;

trait TagsMappingTrait
{
    /**
     * Returns a string with the tags mapping of the specified model
     * @return string
     */
    public function getTagsMapping(): string
    {
        $mapping = config('osm2cai.osmTagsMapping');
        $result = '';

        //if the table has not the tags column search in the osmfeatures_data column for the tags
        $tags = match (true) {
            ! $this->tags && ! $this->osmfeatures_data => [],
            ! $this->tags && $this->osmfeatures_data => is_string($this->osmfeatures_data)
                ? json_decode($this->osmfeatures_data, true)['properties']['osm_tags'] ?? []
                : $this->osmfeatures_data['properties']['osm_tags'] ?? [],
            default => json_decode($this->tags, true)
        };

        if (empty($tags)) {
            return $result;
        }

        foreach ($tags as $key => $value) {
            if (array_key_exists($key, $mapping)) {
                if (array_key_exists($value, $mapping[$key])) {
                    $result .= $mapping[$key][$value].';';
                }
            }
        }

        return substr($result, 0, -1);
    }
}
