<?php

namespace App\Traits\Nova;

use App\Enums\ValidatedStatusEnum;
use App\Nova\App;
use App\Nova\Filters\RelatedUGCFilter;
use App\Nova\Filters\ValidatedFilter;
use App\Nova\Metrics\UgcAppNameDistribution;
use App\Nova\Metrics\UgcAttributeDistribution;
use App\Nova\Metrics\UgcDevicePlatformDistribution;
use App\Nova\Metrics\UgcValidatedStatusDistribution;
use App\Nova\User;
use Carbon\Carbon;
use Ebess\AdvancedNovaMediaLibrary\Fields\Images;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Wm\WmPackage\Nova\Actions\EditFields;
use Wm\WmPackage\Nova\Fields\PropertiesPanel;
use Wm\WmPackage\Nova\Metrics\TopUgcCreators;

trait UgcCommonFieldsTrait
{
    /**
     * Get the common fields shared between UgcTrack and UgcPoi
     */
    protected function getCommonFields(): array
    {
        return [
            ID::make()->sortable(),

            // Created by field with platform icons and version
            Text::make(__('Created by'), 'created_by')
                ->displayUsing(function ($value) {
                    $syncedFromGeoHubLabel = __('Synced from GeoHub');
                    $hasGeoHubId = isset($this->geohub_id) ||
                        (isset($this->properties['geohub_app_id']) && $this->properties['geohub_app_id'] !== null);

                    $geoHubIndicator = $hasGeoHubId
                        ? "<span style='font-size: 16px; margin-right: 4px;' title='{$syncedFromGeoHubLabel}'>🔄</span>"
                        : '';
                    if ($value === 'device') {
                        $version = $this->properties['device']['appVersion'] ?? null;
                        $platform = $this->properties['device']['platform'] ?? null;

                        $outputVersion = $version ? "<span style='margin-right: 8px;'>v{$version}</span>" : '';

                        // Default mobile icon
                        $platformIcon = '<span style="font-size: 16px;">📱</span>';

                        if ($platform) {
                            $platformLower = strtolower($platform);
                            if (str_contains($platformLower, 'android')) {
                                $platformIcon = '<img src="/assets/images/android-icon.png" alt="Android" style="width: 18px; height: 18px; vertical-align: middle; margin-right: 4px;">';
                            } elseif (str_contains($platformLower, 'ios')) {
                                $platformIcon = '<img src="/assets/images/ios-icon.png" alt="iOS" style="width: 18px; height: 18px; vertical-align: middle; margin-right: 4px;">';
                            }
                        }

                        return "<div style='display: inline-flex; align-items: center; white-space: nowrap;'>{$platformIcon}{$outputVersion}{$geoHubIndicator}</div>";
                    } elseif ($value === 'platform') {
                        return "<div style='display: inline-flex; align-items: center; white-space: nowrap;'><span style='font-size: 16px;'>💻</span></div>";
                    }

                    return "<div style='display: inline-flex; align-items: center; white-space: nowrap;'><span style='font-size: 16px;'>❓</span></div>";
                })
                ->asHtml()
                ->hideWhenCreating()
                ->hideWhenUpdating(),

            // App relationship
            BelongsTo::make(__('App'), 'app', App::class)
                ->readonly(function ($request) {
                    return $request->isUpdateOrUpdateAttachedRequest();
                }),

            // Author relationship
            BelongsTo::make(__('Author'), 'author', User::class)
                ->filterable()
                ->searchable()
                ->hideWhenUpdating()
                ->hideWhenCreating(),

            // Name
            Text::make('Name', function () {
                return $this->properties['name'] ?? (isset($this->properties['form']['title']) ? $this->properties['form']['title'] : null);
            }),

            // Validation Status display with emoji
            Text::make(__('Validation Status'), 'validated')
                ->hideWhenCreating()
                ->hideWhenUpdating()
                ->displayUsing(function ($value) {
                    return match ($value) {
                        ValidatedStatusEnum::VALID->value => '<span title="'.__('Valid').'">✅</span>',
                        ValidatedStatusEnum::INVALID->value => '<span title="'.__('Invalid').'">❌</span>',
                        ValidatedStatusEnum::NOT_VALIDATED->value => '<span title="'.__('Not Validated').'">⏳</span>',
                        default => '<span title="'.ucfirst($value).'">❓</span>',
                    };
                })
                ->asHtml(),

            // Validation Date
            DateTime::make(__('Validation Date'), 'validation_date')
                ->onlyOnDetail(),

            // Registered At
            DateTime::make(__('Registered At'), function () {
                return $this->getRegisteredAtAttribute();
            }),

            // Updated At
            DateTime::make(__('Updated At'), 'updated_at')
                ->sortable()
                ->hideWhenUpdating()
                ->hideWhenCreating(),

            // Geohub ID
            Text::make(__('Geohub  ID'), 'geohub_id')
                ->onlyOnDetail(),

            // Validated select with complex logic
            Select::make(__('Validated'), 'validated')
                ->options($this->validatedStatusOptions())
                ->default(ValidatedStatusEnum::NOT_VALIDATED->value)
                ->canSee(function ($request) {
                    $formId = null;
                    $properties = $this->properties ?? [];

                    if (isset($properties['form']['id'])) {
                        $formId = $properties['form']['id'];
                    }

                    return $formId ? $request->user()->isValidatorForFormId($formId) : false;
                })
                ->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                    $isValidated = $request->get($requestAttribute);
                    $model->$attribute = $isValidated;

                    if ($isValidated == ValidatedStatusEnum::VALID->value) {
                        $model->validator_id = $request->user()->id;
                        $model->validation_date = now();
                    } else {
                        $model->validator_id = null;
                        $model->validation_date = null;
                    }
                })
                ->onlyOnForms(),

            // Images
            Images::make(__('Image'), 'default')->hideFromIndex(),

            // Properties panels
            PropertiesPanel::makeWithModel('Form', 'properties->form', $this, true),
            PropertiesPanel::makeWithModel('Nominatim Address', 'properties->nominatim->address', $this, false)->collapsible(),
            PropertiesPanel::makeWithModel('Device', 'properties->device', $this, false)->collapsible()->collapsedByDefault(),
            PropertiesPanel::makeWithModel('Nominatim', 'properties->nominatim', $this, false)->collapsible()->collapsedByDefault(),
            PropertiesPanel::makeWithModel('Properties', 'properties', $this, false)->collapsible()->collapsedByDefault(),
        ];
    }

    /**
     * Get validation status options
     */
    public function validatedStatusOptions(): array
    {
        return Arr::mapWithKeys(ValidatedStatusEnum::cases(), fn ($enum) => [$enum->value => $enum->name]);
    }

    /**
     * Get form ID options based on associated app's acquisition forms
     */
    public function getFormIdOptions(): array
    {
        if (! $this->app) {
            return [];
        }

        $forms = $this->app->acquisitionForms();

        if (! $forms) {
            return [];
        }

        $options = [];
        foreach ($forms as $form) {
            if (isset($form['id'])) {
                $label = $form['name'] ?? $form['label']['it'] ?? $form['label'] ?? $form['id'];
                $options[$form['id']] = $label;
            }
        }

        return $options;
    }

    /**
     * Get the registered at attribute with fallback logic
     */
    public function getRegisteredAtAttribute()
    {
        $properties = $this->properties ?? [];

        if (isset($properties['date'])) {
            return Carbon::parse($properties['date']);
        }
        if (isset($properties['createdAt'])) {
            return Carbon::parse($properties['createdAt']);
        }

        return $this->created_at;
    }

    /**
     * Get common filters
     */
    protected function getCommonFilters(): array
    {
        return [
            new RelatedUGCFilter,
            new ValidatedFilter,
        ];
    }

    /**
     * Get common actions with validation permission check
     */
    protected function getCommonActions(Request $request, string $permissionType): array
    {
        return [
            (new EditFields('Validate Resource', ['validated'], $this))->canSee(function () use ($permissionType) {
                return optional(Auth::user())->hasPermissionTo("validate {$permissionType}");
            }),
        ];
    }

    /**
     * Get the common cards for the resource
     */
    protected function getCommonCards($model): array
    {
        return [
            new TopUgcCreators($model)->width('full')->height('dynamic'),
            new UgcAppNameDistribution($model),
            new UgcAttributeDistribution('App Version', "properties->'device'->>'appVersion'", $model),
            new UgcAttributeDistribution('App Form', "properties->'form'->>'id'", $model),
            new UgcDevicePlatformDistribution($model),
            new UgcValidatedStatusDistribution($model),
        ];
    }
}
