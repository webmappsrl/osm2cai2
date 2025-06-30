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
            Images::make('Image', 'default')->onlyOnDetail(),
            PropertiesPanel::makeWithModel('Form', 'properties->form', $this, true),
            PropertiesPanel::makeWithModel('Nominatim Address', 'properties->nominatim->address', $this, false)->collapsible(),
            PropertiesPanel::makeWithModel('Device', 'properties->device', $this, false)->collapsible()->collapsedByDefault(),
            PropertiesPanel::makeWithModel('Nominatim', 'properties->nominatim', $this, false)->collapsible()->collapsedByDefault(),
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

    public function filters(Request $request): array
    {
        return [
            new RelatedUGCFilter,
            new ValidatedFilter,
            ...parent::filters($request),
        ];
    }
}
