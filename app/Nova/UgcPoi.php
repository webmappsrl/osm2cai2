<?php

namespace App\Nova;

use App\Enums\ValidatedStatusEnum;
use App\Nova\Filters\RelatedUGCFilter;
use App\Nova\Filters\ValidatedFilter;
use Carbon\Carbon;
use Ebess\AdvancedNovaMediaLibrary\Fields\Images;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Wm\MapPoint\MapPoint;
use Wm\WmPackage\Nova\Fields\PropertiesPanel;
use Wm\WmPackage\Nova\UgcPoi as WmUgcPoi;

class UgcPoi extends WmUgcPoi
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\UgcPoi::class;

    /**
     * Get the fields displayed by the resource.
     */
    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Created by', 'created_by')
                ->displayUsing(function ($value) {
                    if ($value === 'device') {
                        return '📱';
                    } elseif ($value === 'platform') {
                        return '💻';
                    }
                    return '❓';
                })->hideWhenCreating()->hideWhenUpdating(),
            BelongsTo::make('App', 'app', App::class)
                ->readonly(function ($request) {
                    return $request->isUpdateOrUpdateAttachedRequest();
                }),
            BelongsTo::make('Author', 'author', User::class)->filterable()->searchable()->hideWhenUpdating()->hideWhenCreating(),
            Text::make('Name', 'properties->name'),
            Text::make(__('Validation Status'), 'validated')
                ->hideWhenCreating()
                ->hideWhenUpdating()
                ->displayUsing(function ($value) {
                    // phpcs:ignore Generic.PHP.Syntax
                    return match ($value) {
                        ValidatedStatusEnum::VALID->value => '<span title="'.__('Valid').'">✅</span>',
                        ValidatedStatusEnum::INVALID->value => '<span title="'.__('Invalid').'">❌</span>',
                        ValidatedStatusEnum::NOT_VALIDATED->value => '<span title="'.__('Not Validated').'">⏳</span>',
                        default => '<span title="'.ucfirst($value).'">❓</span>',
                    };
                })
                ->asHtml(),
            DateTime::make(__('Validation Date'), 'validation_date')
                ->onlyOnDetail(),
            DateTime::make(__('Registered At'), function () {
                return $this->getRegisteredAtAttribute();
            }),
            DateTime::make(__('Updated At'))
                ->sortable()
                ->hideWhenUpdating()
                ->hideWhenCreating(),
            Text::make(__('Geohub  ID'), 'geohub_id')
                ->onlyOnDetail(),
            Select::make(__('Validated'), 'validated')
                ->options($this->validatedStatusOptions())
                ->default(ValidatedStatusEnum::NOT_VALIDATED->value)
                ->canSee(function ($request) {
                    // Controllo se properties, form e id esistono prima di accedere
                    $formId = null;
                    $properties = $this->properties ?? [];

                    if (isset($properties['form']['id'])) {
                        $formId = $properties['form']['id'];
                    }

                    return $formId ? $request->user()->isValidatorForFormId($formId) : false;
                })->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                    $isValidated = $request->get($requestAttribute);
                    $model->$attribute = $isValidated;
                    // logic to track validator and validation date

                    if ($isValidated == ValidatedStatusEnum::VALID->value) {
                        $model->validator_id = $request->user()->id;
                        $model->validation_date = now();
                    } else {
                        $model->validator_id = null;
                        $model->validation_date = null;
                    }
                })->onlyOnForms(),
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

                    // Aggiorna le properties con il nuovo form_id
                    $properties = $model->properties ?? [];
                    if (! isset($properties['form'])) {
                        $properties['form'] = [];
                    }
                    $properties['form']['id'] = $formId;
                    $model->properties = $properties;
                }),
            MapPoint::make('geometry')->withMeta([
                'center' => [43.7125, 10.4013],
                'attribution' => '<a href="https://webmapp.it/">Webmapp</a> contributors',
                'tiles' => 'https://api.webmapp.it/tiles/{z}/{x}/{y}.png',
                'minZoom' => 8,
                'maxZoom' => 14,
                'defaultZoom' => 10,
                'defaultCenter' => [43.7125, 10.4013],
            ])->hideFromIndex()
                ->required(),
            Images::make('Image', 'default')->onlyOnDetail(),
            PropertiesPanel::makeWithModel('Form', 'properties->form', $this, true),
            PropertiesPanel::makeWithModel('Nominatim Address', 'properties->nominatim->address', $this, false)->collapsible(),
            PropertiesPanel::makeWithModel('Device', 'properties->device', $this, false)->collapsible()->collapsedByDefault(),
            PropertiesPanel::makeWithModel('Nominatim', 'properties->nominatim', $this, false)->collapsible()->collapsedByDefault(),
            PropertiesPanel::makeWithModel('Properties', 'properties', $this, false)->collapsible()->collapsedByDefault(),
        ];
    }

    public function validatedStatusOptions()
    {
        return Arr::mapWithKeys(ValidatedStatusEnum::cases(), fn ($enum) => [$enum->value => $enum->name]);
    }

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
     * Ottieni le opzioni per il Form ID select basate sui form disponibili nell'app associata
     */
    public function getFormIdOptions(): array
    {
        // Se non c'è un'app associata, restituisci un array vuoto
        if (! $this->app) {
            return [];
        }

        // Ottieni tutti i form di acquisizione dall'app associata
        $forms = $this->app->acquisitionForms();

        if (! $forms) {
            return [];
        }

        $options = [];
        foreach ($forms as $form) {
            if (isset($form['id'])) {
                // Usa il nome/label del form se disponibile, altrimenti l'id
                $label = $form['name'] ?? $form['label']['it'] ?? $form['label'] ?? $form['id'];
                $options[$form['id']] = $label;
            }
        }

        return $options;
    }

    public function filters(Request $request): array
    {
        return [
            new RelatedUGCFilter,
            new ValidatedFilter,
            ...parent::filters($request),
        ];
    }
}
