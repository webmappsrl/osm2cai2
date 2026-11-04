<?php

namespace App\Models;

use App\Enums\ValidatedStatusEnum;
use App\Services\FlowRateParser;
use App\Traits\UgcCommonModelTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Throwable;
use Wm\WmPackage\Models\UgcPoi as WmUgcPoi;
use Wm\WmPackage\Services\GeometryComputationService;

class UgcPoi extends WmUgcPoi
{
    use UgcCommonModelTrait;

    protected $table = 'ugc_pois';

    protected $fillable = [
        'user_id',
        'app_id',
        'name',
        'geometry',
        'properties',
        'geohub_id',
        'description',
        'raw_data',
        'taxonomy_wheres',
        'validated',
        'water_flow_rate_validated',
        'validator_id',
        'validation_date',
        'note',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->casts = array_merge($this->casts ?? [], [
            'raw_data' => 'array',
        ]);
        $this->initializeUgcCommonModelTrait();
    }

    /**
     * Convert 2D geometry to 3D when setting geometry attribute
     */
    public function setGeometryAttribute($value)
    {
        $this->attributes['geometry'] = GeometryComputationService::make()->convertTo3DGeometry($value);
    }

    /**
     * Surveys of a form: older ones have the form in the form_id column, the ones sent
     * by the app through wm-package (from 27/10/2025) only in properties.form.id.
     * Used by Nova and by the public source survey endpoints, so they select the same surveys.
     */
    public function scopeForForm(Builder $query, string $formId): Builder
    {
        return $query->where(function (Builder $q) use ($formId) {
            $q->where('form_id', $formId)
                ->orWhere('properties->form->id', $formId);
        });
    }

    /**
     * Value of a form field: properties.form for the surveys sent by the app through
     * wm-package, raw_data for the older ones. A blank value falls back to raw_data.
     */
    public function getFormValue(string $key): mixed
    {
        $value = $this->properties['form'][$key] ?? null;
        if ($value !== null && (! is_scalar($value) || trim((string) $value) !== '')) {
            return $value;
        }

        return $this->getRawDataArray()[$key] ?? null;
    }

    /**
     * Date of the survey, in UTC: the device date saved by the current app
     * (properties.createdAt), then the one saved by the old app (raw_data.date),
     * then the date the survey reached the database (created_at), which is usually
     * a few days later. Blank or non ISO 8601 dates are skipped.
     */
    public function getSurveyDate(): ?Carbon
    {
        $candidates = [
            $this->properties['createdAt'] ?? null,
            $this->getRawDataArray()['date'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $date = $this->parseIsoDate($candidate);
            if ($date !== null) {
                return $date;
            }
        }

        return $this->created_at ? Carbon::parse($this->created_at)->utc() : null;
    }

    /**
     * Only ISO 8601 strings (YYYY-MM-DD...): Carbon::parse('') would return "now"
     * and '11/08/2024' would be read as November 8th.
     */
    private function parseIsoDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}/', trim($value))) {
            return null;
        }

        try {
            return Carbon::parse(trim($value))->utc();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Calculates the water flow rate for UGC POIS ACQUASORGENTE and stores it in raw_data
     *
     * @return float|string
     */
    public function calculateFlowRate()
    {
        $flowRate = $this->computeFlowRate();

        // Update raw_data with the calculated flow rate
        $rawData = $this->getRawDataArray();
        $rawData['flow_rate'] = $flowRate;
        $this->raw_data = $rawData;
        $this->saveQuietly();

        return $flowRate;
    }

    /**
     * Computes the water flow rate (L/s) without saving the model.
     * Returns 'N/A' when the flow rate cannot be computed: the rules and the reasons
     * are in evaluateFlowRate().
     */
    public function computeFlowRate(): float|string
    {
        return $this->evaluateFlowRate()[0];
    }

    /**
     * Flow rate to show in Nova: the value, or N/A followed by the reasons
     * why it cannot be computed (e.g. "N/A (volume non inserito)"). Saves nothing.
     */
    public function flowRateForDisplay(): float|string
    {
        [$value, $reasons] = $this->evaluateFlowRate();

        if ($reasons === []) {
            return $value;
        }

        return 'N/A ('.implode('; ', array_map(fn (string $reason) => __($reason), $reasons)).')';
    }

    /**
     * Single source of the flow rate rules: returns the value and, when it is N/A,
     * the untranslated reasons, so the value and its explanation cannot diverge.
     *
     * @return array{0: float|string, 1: list<string>}
     */
    private function evaluateFlowRate(): array
    {
        [$value, $reasons] = $this->evaluateFlowRateData();

        // A flow rate not validated is N/A anyway, but data problems are listed too:
        // otherwise they would appear only after the validation (e.g. survey 8833)
        if ($this->water_flow_rate_validated != ValidatedStatusEnum::VALID->value) {
            return ['N/A', array_merge(['Flow rate not validated'], $reasons)];
        }

        return [$value, $reasons];
    }

    /**
     * Flow rate rules on volume and time only, regardless of the validation.
     *
     * @return array{0: float|string, 1: list<string>}
     */
    private function evaluateFlowRateData(): array
    {
        $rawVolume = $this->getFlowRateInput('range_volume');
        $rawTime = $this->getFlowRateInput('range_time');
        $volumeEmpty = $this->isBlankFlowRateInput($rawVolume);
        $timeEmpty = $this->isBlankFlowRateInput($rawTime);

        if ($volumeEmpty && $timeEmpty) {
            return ['N/A', [$this->isSourceInactive() ? 'Source not active' : 'Volume and time not entered']];
        }

        $volume = $volumeEmpty ? null : FlowRateParser::parseVolume($rawVolume);
        $time = $timeEmpty ? null : FlowRateParser::parseTime($rawTime);
        $volumeInvalid = ! $volumeEmpty && $volume === null;
        $timeInvalid = ! $timeEmpty && $time === null;

        if ($volumeInvalid && $timeInvalid) {
            return ['N/A', ['Invalid volume and time: enter numbers only']];
        }

        $reasons = [];
        if ($volumeEmpty) {
            $reasons[] = 'Volume not entered';
        } elseif ($volumeInvalid) {
            $reasons[] = 'Invalid volume: enter numbers only';
        }
        if ($timeEmpty) {
            $reasons[] = 'Time not entered';
        } elseif ($timeInvalid) {
            $reasons[] = 'Invalid time: enter numbers only';
        }

        if ($reasons !== []) {
            return ['N/A', $reasons];
        }

        if ($time == 0) {
            return ['N/A', ['Time cannot be zero']];
        }

        return [round($volume / $time, 3), []];
    }

    private function isBlankFlowRateInput(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    /**
     * A dry source is surveyed with "active" = no and without volume and time.
     */
    private function isSourceInactive(): bool
    {
        // Same properties.form → raw_data fallback as volume and time
        $active = $this->getFlowRateInput('active');

        return is_string($active) && strtolower(trim($active)) === 'no';
    }

    private function getFlowRateInput(string $key): mixed
    {
        return $this->getFormValue($key);
    }

    private function getRawDataArray(): array
    {
        $rawData = $this->raw_data;
        if (! is_array($rawData)) {
            $rawData = is_string($rawData) ? json_decode($rawData, true) : [];
            $rawData = is_array($rawData) ? $rawData : [];
        }

        return $rawData;
    }
}
