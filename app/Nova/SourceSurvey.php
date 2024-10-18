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

    public static function getAuthorizationMethod(): string
    {
        return 'is_source_validator';
    }

    /**
     * Array of fields to show.
     *
     * @var array
     */
    protected static $activeFields = ['ID', 'User', 'Validated', 'Validation Date', 'Validator', 'geometry', 'Gallery'];

    public function fields(Request $request)
    {
        $fields = parent::fields($request);

        $dedicatedFields = [
            Date::make('Monitoring Date', function () {
                return $this->getRegisteredAtAttribute();
            })->sortable(),
            Text::make('Flow Rate L/s', 'raw_data->flow_rate')->resolveUsing(function ($value) {
                return $this->calculateFlowRate();
            }),
            Text::make('Flow Rate/Volume', 'raw_data->range_volume')->hideFromIndex(),
            Text::make('Flow Rate/Fill Time', 'raw_data->range_time')->hideFromIndex(),
            Text::make('Conductivity microS/cm', 'raw_data->conductivity'),
            Text::make('Temperature °C', 'raw_data->temperature'),
            // Boolean::make('Photos', function () {
            //     return count($this->ugc_media) > 0;
            // })->hideFromDetail(), TODO WITH SPATIE MEDIA LIBRARY (??)
            Select::make('Water Flow Rate Validated', 'water_flow_rate_validated')
                ->options(ValidatedStatusEnum::cases()),
            Textarea::make('Notes', 'note')->hideFromIndex(),
        ];

        return array_merge($fields, $dedicatedFields);
    }

    public function fieldsForUpdate()
    {
        $readonlyFields = $this->readonlyFields();
        $modifiablesFields = $this->modifiablesFields();
        return array_merge($readonlyFields, $modifiablesFields);
    }

    public function readonlyFields()
    {
        $rawData = $this->raw_data;
        return [
            Text::make('ID', 'id')->hideFromIndex()->readonly(),
            Text::make('User', 'user')->resolveUsing(function ($user) {
                return $user->name ?? $this->user_no_match;
            })->readonly(),
            Date::make('Monitoring Date', function () use ($rawData) {
                return $this->getRegisteredAtAttribute();
            })
                ->sortable()->readonly(),
        ];
    }
    public function modifiablesFields()
    {
        return [
            Text::make('Flow Rate/Volume', 'raw_data->range_volume'),
            Text::make('Flow Rate/Fill Time', 'raw_data->range_time'),
            Text::make('Conductivity microS/cm', 'raw_data->conductivity'),
            Text::make('Temperature °C', 'raw_data->temperature'),
            Select::make('Validated', 'validated')
                ->options(ValidatedStatusEnum::cases())
                ->canSee(function ($request) {
                    return $request->user()->isValidatorForFormId($this->form_id) ?? false;
                })->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                    $isValidated = $request->$requestAttribute;
                    $model->$attribute = $isValidated;

                    if ($isValidated == ValidatedStatusEnum::VALID) {
                        $model->validator_id = $request->user()->id;
                        $model->validation_date = now();
                    } else {
                        $model->validator_id = null;
                        $model->validation_date = null;
                    }
                })->default(ValidatedStatusEnum::NOT_VALIDATED),
            Select::make('Water Flow Rate Validated', 'water_flow_rate_validated')
                ->options(ValidatedStatusEnum::cases())
                ->default(ValidatedStatusEnum::NOT_VALIDATED),
            Textarea::make('Notes', 'note'),

        ];
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

    protected function calculateFlowRate()
    {
        if ($this->water_flow_rate_validated === ValidatedStatusEnum::VALID) {

            $rawData = $this->raw_data;


            $volume = $this->formatNumericValue($rawData['flow_rate_volume'] ?? '');
            $time = $this->formatNumericValue($rawData['flow_rate_fill_time'] ?? '');

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
