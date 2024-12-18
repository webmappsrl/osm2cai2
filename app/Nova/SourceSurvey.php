<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Boolean;
use App\Enums\ValidatedStatusEnum;
use App\Nova\AbstractValidationResource;
use App\Nova\Filters\ValidatedFilter;
use App\Nova\Filters\WaterFlowValidatedFilter;

class SourceSurvey extends AbstractValidationResource
{
    public static function getFormId(): string
    {
        return 'water';
    }

    public static function getLabel(): string
    {
        return __('Acqua Sorgente');
    }

    public function fields(Request $request)
    {
        $fields = parent::fields($request);
        $monitoringDateField = Date::make(__('Monitoring Date'), function () {
            return $this->getRegisteredAtAttribute();
        })->sortable()->readonly();
        $temperatureField = Text::make(__('Temperature °C'), 'raw_data->temperature');
        $conductivityField = Text::make(__('Conductivity microS/cm'), 'raw_data->conductivity');
        $flowRateVolumeField = Text::make('Flow Rate/Volume', 'raw_data->range_volume')->hideFromIndex();
        $flowRateFillTimeField = Text::make('Flow Rate/Fill Time', 'raw_data->range_time')->hideFromIndex();
        $hasPhotosField = Boolean::make(__('Has Photos'), function () {
            return $this->ugc_media->isNotEmpty();
        })->hideFromDetail();
        $flowRateField = Text::make(__('Flow Rate L/s'), 'raw_data->flow_rate')->resolveUsing(function ($value) {
            return $this->calculateFlowRate();
        })->readonly()->help(__('This data is automatically calculated based on the entered data'));
        $waterFlowRateValidatedField = Select::make(__('Flow Rate Validation'), 'water_flow_rate_validated')->options($this->validatedStatusOptions());
        $noteField = Text::make(__('Notes'), 'note')->hideFromIndex();

        //the following fields are added to the ACQUA SORGENTE panel
        $flowRateField->panel = __('ACQUA SORGENTE');
        $waterFlowRateValidatedField->panel = __('ACQUA SORGENTE');

        $tabIndex = array_search(\DKulyk\Nova\Tabs::class, array_map('get_class', $fields));
        if ($tabIndex !== false) {
            $tab = $fields[$tabIndex];
            array_push($tab->data, $flowRateField);
            array_push($tab->data, $waterFlowRateValidatedField);
        }

        return array_merge($fields, [$monitoringDateField, $temperatureField, $conductivityField, $hasPhotosField, $flowRateVolumeField, $flowRateFillTimeField, $noteField]);
    }

    public function filters(Request $request)
    {
        return [
            (new ValidatedFilter),
            (new WaterFlowValidatedFilter),
        ];
    }

    /**
     * Calculate the flow rate based on the raw data.
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
        } else {
            $rawData = $this->raw_data;
            $rawData['flow_rate'] = 'N/A';
            $this->raw_data = $rawData;
            $this->save();

            return 'N/A';
        }
    }

    private function formatNumericValue($value)
    {
        if (strpos($value, '.') !== false) {
            return $value;
        } else {
            $value = preg_replace('/[^0-9,]/', '', $value);

            return str_replace(',', '.', $value);
        }
    }

    /**
     * Get the fields available for CSV export.
     *
     * @return array
     */
    public static function getExportFields(): array
    {
        return array_merge(parent::getExportFields(), [
            'raw_data->flow_rate' => __('Flow Rate L/s'),
            'raw_data->conductivity' => __('Conductivity microS/cm'),
            'raw_data->temperature' => __('Temperature °C'),
            'water_flow_rate_validated' => __('Flow Rate Validation'),
            'note' => __('Notes'),
        ]);
    }
}
