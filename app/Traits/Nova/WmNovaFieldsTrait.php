<?php

namespace App\Traits\Nova;

use Eminiarts\Tabs\Tabs;
use Laravel\Nova\Fields\Field;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

trait WmNovaFieldsTrait
{
    public function jsonForm(string $columnName, array $formSchema = null)
    {
        $this->ensureNovaIsInstalled();

        if ($formSchema === null) {
            $config = Cache::remember('geohub_config', 60 * 60, function () {
                $config = [];
                $geohubConfigApis = config('geohub.configs');

                foreach ($geohubConfigApis as $appId => $url) {
                    $response = Http::get($url);
                    $jsonResponse = $response->json()['APP'];
                    $poiAcquisitionForm = $jsonResponse['poi_acquisition_form'];
                    $trackAcquisitionForm = $jsonResponse['track_acquisition_form'] ?? [];

                    $config['poi'] = array_merge($config['poi'] ?? [], $poiAcquisitionForm);
                    $config['track'] = array_merge($config['track'] ?? [], $trackAcquisitionForm);
                }

                // Remove duplicates
                $config['poi'] = array_unique($config['poi'], SORT_REGULAR);
                $config['track'] = array_unique($config['track'], SORT_REGULAR);

                return $config;
            });
            //get the model type searching in the request path
            $modelType = str_contains(request()->path(), 'ugc-tracks') ? 'track' : 'poi';

            if ($modelType === 'track') {
                $formConfig = $config['track'][0] ?? null; // Take the first element of the track array
            } else {
                // Search for the corresponding element based on the form_id
                $formConfig = collect($config['poi'])->firstWhere('id', $this->form_id);
            }

            if (! $formConfig) {
                return $this->createNoDataField();
            }

            $fields = $this->buildFieldsFromConfig($formConfig['fields'], $columnName, $modelType);

            $tabsLabel = $formConfig['label']['it'] ?? $formConfig['label']['ït'] ?? $formConfig['label']['en'] ?? __('Form');
        } else {
            $fields = $this->buildFieldsFromConfig($formSchema, $columnName, $this instanceof \App\Models\UgcTrack ? 'track' : 'poi');
            $tabsLabel = __('Validation Permissions');
        }

        $tabs = new Tabs($tabsLabel, [
            $tabsLabel => $fields,
        ]);

        return $tabs;
    }

    protected function buildFieldsFromConfig(array $fieldsConfig, string $columnName, string $modelType): array
    {
        $fields = [];

        foreach ($fieldsConfig as $fieldSchema) {
            if ($fieldSchema['name'] == 'waypointtype' && $modelType == 'poi') {
                array_push(
                    $fieldSchema['values'],
                    [
                        'value' => 'flora',
                        'label' => [
                            'it' => 'Flora',
                            'en' => 'Flora',
                        ],
                    ],
                    [
                        'value' => 'fauna',
                        'label' => [
                            'it' => 'Fauna',
                            'en' => 'Fauna',
                        ],
                    ],
                    [
                        'value' => 'habitat',
                        'label' => [
                            'it' => 'Habitat',
                            'en' => 'Habitat',
                        ],
                    ],
                );
            }
            $novaField = $this->createFieldFromSchema($fieldSchema, $columnName);
            if ($novaField) {
                $fields[] = $novaField;
            }
        }

        return $fields;
    }

    protected function createFieldFromSchema(array $fieldSchema, string $columnName): ?Field
    {
        $key = $fieldSchema['name'] ?? null;
        $fieldType = $fieldSchema['type'] ?? 'text';
        $label = $fieldSchema['label']['it'] ?? $fieldSchema['label']['ït'] ?? $fieldSchema['label']['en'] ?? $key;
        $rules = $this->defineRules($fieldSchema);

        $field = null;

        switch ($fieldType) {
            case 'number':
                $field = \Laravel\Nova\Fields\Number::make(__($label), "$columnName->$key");
                break;
            case 'password':
                $field = \Laravel\Nova\Fields\Password::make(__($label), "$columnName->$key");
                break;
            case 'select':
                $options = $this->getSelectOptions($fieldSchema);
                $field = \Laravel\Nova\Fields\Select::make(__($label), "$columnName->$key")
                    ->options($options)
                    ->displayUsingLabels();
                break;
            case 'boolean':
                $field = \Laravel\Nova\Fields\Boolean::make($label, "$columnName->$key");
                break;
            case 'textarea':
                $field = \Laravel\Nova\Fields\Textarea::make(__($label), "$columnName->$key");
                break;
            default:
                $field = \Laravel\Nova\Fields\Text::make(__($label), "$columnName->$key");
        }

        if ($field) {
            $field->rules($rules)->hideFromIndex();

            if (isset($fieldSchema['helper'])) {
                $field->help($fieldSchema['helper']['it'] ?? $fieldSchema['helper']['en'] ?? '');
            }

            if (isset($fieldSchema['placeholder'])) {
                $field->placeholder($fieldSchema['placeholder']['it'] ?? $fieldSchema['placeholder']['en'] ?? '');
            }
        }

        return $field;
    }

    protected function getSelectOptions(array $fieldSchema): array
    {
        $options = [];
        if (isset($fieldSchema['values'])) {
            foreach ($fieldSchema['values'] as $option) {
                $options[$option['value']] = $option['label']['it'] ?? $option['label']['en'] ?? $option['value'];
            }
        }

        return $options;
    }

    protected function defineRules(array $fieldSchema): array
    {
        $rules = [];
        if (isset($fieldSchema['required']) && $fieldSchema['required']) {
            $rules[] = 'required';
        }

        return $rules;
    }

    protected function createNoDataField(): Field
    {
        return
            \Laravel\Nova\Fields\Text::make(__('No data for this form ID'), function () {
                return '/';
            })->hideFromIndex();
    }

    protected function ensureNovaIsInstalled()
    {
        if (! class_exists('Laravel\Nova\Fields\Field')) {
            throw new \Exception('Laravel Nova is not installed. Please install Laravel Nova to use this feature.');
        }
    }
}
