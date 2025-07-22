<?php

namespace App\Nova;

use App\Enums\ValidatedStatusEnum;
use App\Nova\Filters\RelatedUGCFilter;
use App\Nova\Filters\ValidatedFilter;
use Ebess\AdvancedNovaMediaLibrary\Fields\Images;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Wm\Osm2caiMapMultiLinestring\Osm2caiMapMultiLinestring;
use Wm\WmPackage\Nova\Actions\EditFields;
use Wm\WmPackage\Nova\UgcTrack as WmUgcTrack;
use Illuminate\Support\Facades\Auth;
use Wm\WmPackage\Nova\Fields\PropertiesPanel;

class UgcTrack extends WmUgcTrack
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\UgcTrack::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public function title()
    {
        if ($this->name) {
            return "{$this->name} ({$this->id})";
        } else {
            return "{$this->id}";
        }
    }

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'name',
    ];

    public static function label()
    {
        $label = 'Track';

        return __($label);
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @return array
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
            Images::make('Image', 'default')->onlyOnDetail(),
            PropertiesPanel::makeWithModel('Form', 'properties->form', $this, true),
            PropertiesPanel::makeWithModel('Nominatim Address', 'properties->nominatim->address', $this, false)->collapsible(),
            PropertiesPanel::makeWithModel('Device', 'properties->device', $this, false)->collapsible()->collapsedByDefault(),
            PropertiesPanel::makeWithModel('Nominatim', 'properties->nominatim', $this, false)->collapsible()->collapsedByDefault(),
            PropertiesPanel::makeWithModel('Properties', 'properties', $this, false)->collapsible()->collapsedByDefault(),
        ];
    }

    public function additionalFields(Request $request)
    {
        $centroid = $this->getCentroid();
        $geojson = $this->getGeojsonForMapView();
        $fields = [
            Text::make(__('Taxonomy Where'), function ($model) {
                $wheres = $model->taxonomy_wheres;
                $words = explode(' ', $wheres);
                $lines = array_chunk($words, 3);
                $formattedWheres = implode('<br>', array_map(function ($line) {
                    return implode(' ', $line);
                }, $lines));

                return $formattedWheres;
            })->asHtml()
                ->onlyOnDetail(),
            $this->getCodeField('Raw data'),
            $this->getCodeField('Metadata'),
            Osm2caiMapMultiLinestring::make('geometry')->withMeta([
                'center' => $centroid ?? [42, 10],
                'attribution' => '<a href="https://webmapp.it/">Webmapp</a> contributors',
                'tiles' => 'https://api.webmapp.it/tiles/{z}/{x}/{y}.png',
                'defaultZoom' => 10,
                'geojson' => json_encode($geojson),
            ])->hideFromIndex(),
        ];

        return $fields;
    }

    /**
     * Get the cards available for the request.
     *
     * @return array
     */
    public function cards(Request $request): array
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array
     */
    public function lenses(Request $request): array
    {
        return [];
    }

    public function validatedStatusOptions()
    {
        return Arr::mapWithKeys(ValidatedStatusEnum::cases(), fn ($enum) => [$enum->value => $enum->name]);
    }
    /**
     * Get the actions available for the resource.
     *
     * @return array
     */
    public function actions(Request $request): array
    {
        $parentActions = parent::actions($request);
        $specificActions = [
            (new EditFields('Validate Resource', ['validated'], $this))->canSee(function () {
                return optional(Auth::user())->hasPermissionTo('validate tracks');
            }),
        ];

        return array_merge($parentActions, $specificActions);
    }

    public static function getExportFields(): array
    {
        return array_merge(parent::getExportFields(), [
            'raw_data->latitude' => __('Latitudine'),
            'raw_data->longitude' => __('Longitudine'),
        ]);
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
