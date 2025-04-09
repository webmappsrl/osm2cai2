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
use Laravel\Nova\Panel;

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
        return __('Acqua Sorgente');
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
        $fields = $this->integrateFieldsIntoPanel($fields, $surveyFields);

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
    private function integrateFieldsIntoPanel(array $fields, array $surveyFields): array
    {
        // Find the existing Panel in the fields
        foreach ($fields as $key => $field) {
            if ($field instanceof Panel) {
                // Add survey fields to the existing panel
                $existingFields = $field->data;
                $updatedFields = array_merge($existingFields, $surveyFields);

                // Replace the existing panel with updated fields
                $fields[$key] = Panel::make($field->name, $updatedFields);
                return $fields;
            }
        }

        // If no panel exists, create a new one
        $fields[] = Panel::make(__('Details'), $surveyFields);

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
