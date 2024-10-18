<?php

namespace App\Nova;

use Laravel\Nova\Fields\ID;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Text;
use Illuminate\Support\Carbon;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Boolean;
use App\Enums\UgcValidatedStatus;
use Laravel\Nova\Fields\Textarea;
use App\Enums\ValidatedStatusEnum;
use Wm\MapPointNova3\MapPointNova3;
use App\Nova\Actions\DownloadUgcCsv;
use Illuminate\Support\Facades\Auth;
use App\Nova\Filters\ValidatedFilter;
use App\Nova\AbstractValidationResource;
use App\Enums\UgcWaterFlowValidatedStatus;
use Laravel\Nova\Http\Requests\NovaRequest;
use App\Nova\Filters\WaterFlowValidatedFilter;

class SourceSurvey extends AbstractValidationResource
{
    public static function getFormId(): string
    {
        return 'water';
    }

    public static function getLabel(): string
    {
        return 'Acqua Sorgente';
    }


    // /**
    //  * Array of fields to show.
    //  *
    //  * @var array
    //  */
    // protected static $activeFields = ['ID', 'User', 'Validated', 'Validation Date', 'Validator', 'geometry', 'Gallery'];

    public function fields(Request $request)
    {
        $fields = parent::fields($request);

        $flowRateField = Text::make('Portata L/s', 'raw_data->flow_rate')->resolveUsing(function ($value) {
            return $this->calculateFlowRate();
        })->readonly()->help('Questo dato viene calcolato automaticamente in base ai dati inseriti');
        $waterFlowRateValidatedField = Select::make('Validazione portata', 'water_flow_rate_validated')->options($this->validatedStatusOptions());

        $flowRateField->panel = 'ACQUA SORGENTE';
        $waterFlowRateValidatedField->panel = 'ACQUA SORGENTE';

        $tabIndex = array_search(\DKulyk\Nova\Tabs::class, array_map('get_class', $fields));
        if ($tabIndex !== false) {
            $tab = $fields[$tabIndex];
            array_push($tab->data, $flowRateField);
            array_push($tab->data, $waterFlowRateValidatedField);
        }

        return $fields;
    }


    public function filters(Request $request)
    {
        return [
            (new ValidatedFilter),
            (new WaterFlowValidatedFilter)
        ];
    }


    /**
     * Get the actions available for the resource.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function actions(Request $request)
    {
        return [
            (new DownloadUgcCsv($this)),
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
            'raw_data->flow_rate' => 'Portata L/s',
            'raw_data->conductivity' => 'Conducibilità microS/cm',
            'raw_data->temperature' => 'Temperatura °C',
            'water_flow_rate_validated' => 'Validazione portata',
            'note' => 'Note',
        ]);
    }
}
