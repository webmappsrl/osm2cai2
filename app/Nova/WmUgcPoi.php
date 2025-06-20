<?php

namespace App\Nova;

use App\Enums\ValidatedStatusEnum;
use App\Nova\Filters\RelatedUGCFilter;
use App\Nova\Filters\ValidatedFilter;
use Carbon\Carbon;
use Ebess\AdvancedNovaMediaLibrary\Fields\Images;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Wm\MapPoint\MapPoint;
use Wm\WmPackage\Nova\Fields\PropertiesPanel;
use Wm\WmPackage\Nova\UgcPoi as NovaUgcPoi;

class WmUgcPoi extends NovaUgcPoi
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\WmUgcPoi::class;

    /**
     * Get the fields displayed by the resource.
     */
    public function fields(Request $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('App', 'app', App::class),
            BelongsTo::make('Author', 'author', User::class)->filterable()->searchable()->hideWhenUpdating()->hideWhenCreating(),
            Text::make('Name', 'properties->name'),
            Text::make(__('Validation Status'), 'validated')
                ->hideWhenCreating()
                ->hideWhenUpdating(),
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
                    return $request->user()->isValidatorForFormId($this->properties['form']['id']) ?? false;
                })->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                    $isValidated = $request->$requestAttribute;
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
            Text::make('Form ID', 'form_id')->resolveUsing(function ($value) {
                if ($this->properties and isset($this->properties['form']['id'])) {
                    return $this->properties['form']['id'];
                } else {
                    return $value;
                }
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
            PropertiesPanel::make('properties', 'form')->collapsible(),
            Images::make('Image', 'default')->onlyOnDetail(),
        ];
    }

    public function validatedStatusOptions()
    {
        return Arr::mapWithKeys(ValidatedStatusEnum::cases(), fn ($enum) => [$enum->value => $enum->name]);
    }

    public function getRegisteredAtAttribute()
    {
        if (isset($this->properties['date'])) {
            return Carbon::parse($this->properties['date']);
        }

        if (isset($this->properties['createdAt'])) {
            return Carbon::parse($this->properties['createdAt']);
        }

        return $this->created_at;
    }

    public function jsonForm(string $columnName, string $attribute, ?array $formSchema = null)
    {
        // Ensure Laravel Nova is installed
        $this->ensureNovaIsInstalled();

        $fields = [];

        if ($columnName && Schema::hasColumn($this->getTable(), $columnName)) {
            // Fetch the JSON data from the column
            $column = $this->$columnName ?? '';
            if (! is_array($column)) {
                $formData = json_decode($column, true) ?? [];
            } else {
                $formData = $column;
            }
            if (is_null($formSchema) || empty($formSchema)) {
                // If no form schema is provided, use the form data directly
                foreach ($formData as $key => $value) {
                    // Create a dummy schema based on existing form data
                    $fieldSchema = [
                        'name' => $key,
                        'type' => is_numeric($value) ? 'number' : 'text',
                        'value' => $value,
                    ];
                    $novaField = $this->createFieldFromSchema($fieldSchema, $columnName);
                    if ($novaField) {
                        $fields[] = $novaField;
                    }
                }
            } else {
                // Initialize the fields with data from the JSON column
                foreach ($formSchema as $fieldSchema) {
                    $label = $fieldSchema['label'];
                    $value = $formData[$label] ?? $fieldSchema['value'] ?? null;
                    $fieldSchema['value'] = $value; // Set the value from form data or default
                    $novaField = $this->createFieldFromSchema($fieldSchema, $columnName);
                    if ($novaField) {
                        $fields[] = $novaField;
                    }
                }
            }
        } elseif ($formSchema) {
            // Use the provnameed form schema
            foreach ($formSchema as $fieldSchema) {
                $novaField = $this->createFieldFromSchema($fieldSchema);
                if ($novaField) {
                    $fields[] = $novaField;
                }
            }
        } else {
            throw new \Exception('Either form JSON column name or form schema must be provnameed. Please check your database or
provnamee a form schema.');
        }

        return $fields;
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
