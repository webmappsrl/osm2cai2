<?php

namespace App\Traits\Nova;

use Laravel\Nova\Fields\Code;

/**
 * Trait RawDataFieldsTrait
 * 
 * This trait provides methods for handling raw data fields in JSON format.
 */
trait RawDataFieldsTrait
{

    /**
     * Get the code field.
     * 
     * This method returns a code field in JSON format including only the specified keys.
     * 
     * @param string $fieldName The name of the field.
     * @param array $includeKeys The keys to include in the field.
     * @return Code
     */
    protected function getCodeField(string $fieldName, array $includeKeys = [])
    {
        return Code::make(__($fieldName), function ($model) use ($includeKeys, $fieldName) {
            return $this->encodeFilteredJsonData($model, $includeKeys, $fieldName);
        })->onlyOnDetail()->language('json')->rules('json');
    }


    /**
     * Get the metadata field.
     * 
     * This method returns a metadata field in JSON format, if present (for tracks).
     * 
     * @return Code
     */
    protected function getMetadataField()
    {
        return Code::make(__('Metadata'), function ($model) {
            return $model->metaData ? json_encode($model->metaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null;
        })->onlyOnDetail()->language('json')->rules('json');
    }


    /**
     * Encode filtered JSON data.
     * 
     * This method encodes the filtered JSON data based on the provided include keys.
     * 
     * @param $model
     * @param array $includeKeys
     * @param string $fieldName
     * @return string
     */
    protected function encodeFilteredJsonData($model, $includeKeys, $fieldName)
    {
        $jsonRawData = $this->getJsonRawData($model, $fieldName);
        if (!$jsonRawData) {
            return null;
        }

        $filteredData = empty($includeKeys) ? $jsonRawData : array_intersect_key($jsonRawData, array_flip($includeKeys));
        return json_encode($filteredData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get the raw data in JSON format.
     * 
     * This method returns the raw data in JSON format, decoding the JSON string if necessary.
     * 
     * @param $model
     * @return array|null
     */
    protected function getJsonRawData($model, $fieldName)
    {
        $rawData = is_string($model->raw_data) ? json_decode($model->raw_data, true) : $model->raw_data ?? null;
        if ($fieldName === 'Nominatim') {
            return $rawData ? $rawData['nominatim'] ?? null : null;
        }
        return $rawData;
    }
}
