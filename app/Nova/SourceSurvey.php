<?php

namespace App\Nova;

use App\Enums\ValidatedStatusEnum;
use App\Nova\AbstractValidationResource;
use App\Nova\Filters\ValidatedFilter;
use App\Nova\Filters\WaterFlowValidatedFilter;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Eminiarts\Tabs\Tab;
use Eminiarts\Tabs\Tabs;

class SourceSurvey extends AbstractValidationResource
{
    /**
     * Returns the form identifier.
     *
     * @return string
     */
    public static function getFormId(): string
    {
        return 'water';
    }

    /**
     * Returns the form label.
     *
     * @return string
     */
    public static function getLabel(): string
    {
        return __('Water Source');
    }

    /**
     * Gets the fields for the resource.
     *
     * @param Request $request
     * @return array
     */
    public function fields(Request $request)
    {
        // Retrieves fields from parent class
        $fields = parent::fields($request);

        // Defines the field to check for the presence of photos
        $hasPhotosField = Boolean::make(__('Has Photos'), function () {
            return $this->ugc_media->isNotEmpty();
        })->hideFromIndex();

        // Preparation of acqua sorgente specific fields
        $surveyFields = $this->prepareSurveyFields();

        // Integration of fields into tabs
        $fields = $this->integrateFieldsIntoTabs($fields, $surveyFields);

        return array_merge($fields, [$hasPhotosField]);
    }

    /**
     * Prepares the specific fields for the water source.
     *
     * @return array
     */
    private function prepareSurveyFields(): array
    {
        // Water flow rate field (calculated)
        $flowRateField = Text::make(__('Flow Rate L/s'), 'raw_data->flow_rate')
            ->resolveUsing(function ($value) {
                return $this->calculateFlowRate();
            })
            ->readonly()
            ->help(__('This data is automatically calculated based on the entered data'));

        // Field for flow rate validation
        $waterFlowRateValidatedField = Select::make(__('Flow Rate Validation'), 'water_flow_rate_validated')
            ->options($this->validatedStatusOptions());

        return [
            $flowRateField,
            $waterFlowRateValidatedField
        ];
    }

    /**
     * Integrates specific fields into existing tabs or creates new tabs.
     *
     * @param array $fields Array of existing fields
     * @param array $surveyFields Specific fields to add
     * @return array
     */
    private function integrateFieldsIntoTabs(array $fields, array $surveyFields): array
    {
        // Checks if a Tabs object already exists in the fields
        $tabIndex = array_search(Tabs::class, array_map('get_class', $fields));

        // Creates a tab for water source fields
        $aquaTab = Tab::make(__('WATER SOURCE'), $surveyFields);

        if ($tabIndex !== false) {
            // If a tab already exists, integrate the new one
            $existingTab = $fields[$tabIndex];

            // Creates a new Tabs object combining existing tabs with the new one
            $newTabs = new Tabs(
                __('Details'),
                array_merge([$aquaTab], $existingTab->tabs)
            );

            // Replaces the existing tab with the updated one
            $fields[$tabIndex] = $newTabs;
        } else {
            // If no tab exists, create a new one
            $fields[] = new Tabs(__('Details'), [$aquaTab]);
        }

        return $fields;
    }

    /**
     * Defines the filters available for the resource.
     *
     * @param Request $request
     * @return array
     */
    public function filters(Request $request)
    {
        return [
            new ValidatedFilter,
            new WaterFlowValidatedFilter,
        ];
    }

    /**
     * Calculates the water flow rate based on raw data.
     *
     * @return string
     */
    protected function calculateFlowRate()
    {
        if ($this->water_flow_rate_validated == ValidatedStatusEnum::VALID->value) {
            $rawData = $this->raw_data;

            $volume = $this->formatNumericValue($rawData['range_volume'] ?? '');
            $time = $this->formatNumericValue($rawData['range_time'] ?? '');

            if (is_numeric($volume) && is_numeric($time) && $time != 0) {
                $flowRate = round($volume / $time, 3);
            } else {
                $flowRate = 'N/A';
            }

            $rawData['flow_rate'] = $flowRate;

            $this->raw_data = $rawData;
            $this->save();

            return $flowRate;
        }

        // If not validated, returns N/A
        $rawData = $this->raw_data;
        $rawData['flow_rate'] = 'N/A';
        $this->raw_data = $rawData;
        $this->save();

        return 'N/A';
    }

    /**
     * Formats a numeric value for calculation.
     *
     * @param string $value
     * @return string
     */
    private function formatNumericValue($value)
    {
        if (strpos($value, '.') !== false) {
            return $value;
        }

        $value = preg_replace('/[^0-9,]/', '', $value);
        return str_replace(',', '.', $value);
    }

    /**
     * Gets the fields available for CSV export.
     *
     * @return array
     */
    public static function getExportFields(): array
    {
        return array_merge(parent::getExportFields(), [
            'raw_data.flow_rate' => __('Flow Rate L/s'),
            'raw_data.conductivity' => __('Conductivity microS/cm'),
            'raw_data.temperature' => __('Temperature °C'),
            'water_flow_rate_validated' => __('Flow Rate Validation'),
            'note' => __('Notes'),
        ]);
    }
}
