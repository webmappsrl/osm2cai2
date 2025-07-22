<?php

namespace App\Traits\Nova;

use App\Enums\ValidatedStatusEnum;
use App\Nova\App;
use App\Nova\Filters\RelatedUGCFilter;
use App\Nova\Filters\ValidatedFilter;
use App\Nova\User;
use Carbon\Carbon;
use Ebess\AdvancedNovaMediaLibrary\Fields\Images;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Wm\WmPackage\Nova\Actions\EditFields;
use Wm\WmPackage\Nova\Fields\PropertiesPanel;
use Illuminate\Support\Facades\Auth;

trait UgcCommonFieldsTrait
{
    /**
     * Get the common fields shared between UgcTrack and UgcPoi
     */
    protected function getCommonFields(): array
    {
        return [
            ID::make()->sortable(),
            
            // Created by field with emoji
            Text::make('Created by', 'created_by')
                ->displayUsing(function ($value) {
                    if ($value === 'device') {
                        return '📱';
                    } elseif ($value === 'platform') {
                        return '💻';
                    }
                    return '❓';
                })->hideWhenCreating()->hideWhenUpdating(),
            
            // App relationship
            BelongsTo::make('App', 'app', App::class)
                ->readonly(function ($request) {
                    return $request->isUpdateOrUpdateAttachedRequest();
                }),
            
            // Author relationship
            BelongsTo::make('Author', 'author', User::class)
                ->filterable()
                ->searchable()
                ->hideWhenUpdating()
                ->hideWhenCreating(),
            
            // Name from properties
            Text::make('Name', 'properties->name'),
            
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
            DateTime::make(__('Updated At'))
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
            
            // Form ID select with complex logic
            Select::make('Form ID', 'form_id')
                ->options($this->getFormIdOptions())
                ->hideWhenCreating()
                ->resolveUsing(function ($value) {
                    if (isset($this->properties['form']['id'])) {
                        return $this->properties['form']['id'];
                    } else {
                        return $value;
                    }
                })
                ->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                    $formId = $request->$requestAttribute;

                    $properties = $model->properties ?? [];
                    if (! isset($properties['form'])) {
                        $properties['form'] = [];
                    }
                    $properties['form']['id'] = $formId;
                    $model->properties = $properties;
                }),
            
            // Images
            Images::make('Image', 'default')->onlyOnDetail(),
            
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
} 