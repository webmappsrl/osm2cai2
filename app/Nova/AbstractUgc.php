<?php

namespace App\Nova;

use App\Enums\ValidatedStatusEnum;
use App\Nova\Actions\DeleteUgcMedia;
use App\Nova\Actions\DownloadFeatureCollection;
use App\Nova\Actions\UploadAndAssociateUgcMedia;
use App\Nova\Filters\RelatedUGCFilter;
use App\Nova\Filters\UgcAppIdFilter;
use App\Nova\Filters\ValidatedFilter;
use App\Nova\UgcTrack as UgcTrackResource;
use App\Traits\Nova\RawDataFieldsTrait;
use App\Traits\Nova\WmNovaFieldsTrait;
use Idez\DateRangeFilter\DateRangeFilter;
use Idez\DateRangeFilter\Enums\Config;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Resource;

abstract class AbstractUgc extends Resource
{
    use RawDataFieldsTrait, WmNovaFieldsTrait;

    public static $search = [
        'id',
        'name',
    ];

    public static $searchRelations = [
        'user' => ['name', 'email'],
    ];

    /**
     * Get the options for the validated status field.
     *
     * @return array
     */
    public function validatedStatusOptions()
    {
        return Arr::mapWithKeys(ValidatedStatusEnum::cases(), fn ($enum) => [$enum->value => $enum->name]);
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @param Request $request
     * @return array
     */
    public function fields(Request $request)
    {
        $novaFields = [
            ID::make(__('ID'), 'id')->sortable()->readonly(),
            Text::make('User', function () use ($request) {
                if ($this->user_id) {
                    if (auth()->user()->isValidatorForFormId($this->form_id)) {
                        //add the email of the user next to the name for validator
                        return '<a style="text-decoration:none; font-weight:bold; color:teal;" href="/resources/users/'.$this->user_id.'">'.$this->user->name.' ('.$this->user->email.')'.'</a>';
                    }

                    return '<a style="text-decoration:none; font-weight:bold; color:teal;" href="/resources/users/'.$this->user_id.'">'.$this->user->name.'</a>';
                } else {
                    return $this->user->email ?? 'N/A';
                }
            })->asHtml(),
            BelongsTo::make('User', 'user', User::class)
                ->searchable()
                ->hideWhenUpdating()
                ->hideWhenCreating()
                ->hideFromIndex()
                ->hideFromDetail(),
            Select::make(__('Validated'), 'validated')
                ->options($this->validatedStatusOptions())
                ->default(ValidatedStatusEnum::NOT_VALIDATED->value)
                ->canSee(function ($request) {
                    if ($this instanceof UgcTrackResource) {
                        return $request->user()->hasPermissionTo('validate tracks') ?? false;
                    } else {
                        return $request->user()->isValidatorForFormId($this->form_id) ?? false;
                    }
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
            Text::make(__('Validation Status'), 'validated')
                ->hideWhenCreating()
                ->hideWhenUpdating(),
            DateTime::make(__('Validation Date'), 'validation_date')
                ->onlyOnDetail(),
            Text::make('Validator', function () {
                if ($this->validator_id) {
                    return $this->validator->name;
                } else {
                    return null;
                }
            })->onlyOnDetail(),
            Text::make(__('App ID'), 'app_id')
                ->onlyOnDetail(),
            DateTime::make(__('Registered At'), function () {
                return $this->getRegisteredAtAttribute();
            })
                ->readonly(),
            DateTime::make(__('Updated At'))
                ->sortable()
                ->hideWhenUpdating()
                ->hideWhenCreating(),
            Text::make(__('Geohub ID'), 'geohub_id')
                ->onlyOnDetail(),
            Text::make(__('Gallery'), function () {
                $images = $this->ugc_media;
                if (empty($images)) {
                    return 'N/A';
                }
                $html = '<div style="display: flex; flex-wrap: wrap; gap: 10px; padding: 10px;">';
                foreach ($images as $image) {
                    $url = $image->getUrl();
                    $html .= '<div style="margin: 5px; text-align: center; min-width: 100px;">';
                    $html .= '<a href="'.$url.'" target="_blank" style="display: block;">';
                    $html .= '<img src="'.$url.'" width="100" height="100" style="object-fit: cover; display: block; border: 1px solid #ddd; border-radius: 4px;">';
                    $html .= '</a>';
                    $html .= '<p style="margin-top: 5px; color: #666; font-size: 12px;">ID: '.$image->id.'</p>';
                    $html .= '</div>';
                }
                $html .= '</div>';

                return $html;
            })->asHtml()->onlyOnDetail(),
        ];

        $formFields = $this->jsonForm('raw_data');

        if (! empty($formFields)) {
            array_push(
                $novaFields,
                $formFields,
            );
        }

        return $novaFields;
    }

    public function filters(Request $request)
    {
        return [
            new RelatedUGCFilter,
            new ValidatedFilter,
            new UgcAppIdFilter,
            new DateRangeFilter('Created at', 'created_at', [
                Config::ALLOW_INPUT => false,
                Config::DATE_FORMAT => 'd/m/Y',
                Config::DISABLED => false,
                Config::ENABLE_TIME => false,
                Config::ENABLE_SECONDS => false,
                Config::FIRST_DAY_OF_WEEK => 1,
                Config::LOCALE => 'it',
                Config::MAX_DATE => now()->format('d/m/Y'),
                Config::MIN_DATE => '01/01/2019',
                Config::PLACEHOLDER => __('Choose date range'),
                Config::SHORTHAND_CURRENT_MONTH => false,
                Config::SHOW_MONTHS => 1,
                Config::TIME24HR => true,
                Config::WEEK_NUMBERS => false,
            ]),
        ];
    }

    public function actions(Request $request)
    {
        return [
            (new UploadAndAssociateUgcMedia())->canSee(function ($request) {
                if ($this->user_id) {
                    return auth()->user()->id == $this->user_id && $this->validated === ValidatedStatusEnum::NOT_VALIDATED->value;
                }

                return $request->has('resources');
            })
                ->canRun(function ($request) {
                    return true;
                })
                ->confirmText(__('Are you sure you want to upload this image?'))
                ->confirmButtonText(__('Upload'))
                ->cancelButtonText(__('Cancel'))
                ->onlyOnDetail(),
            (new DeleteUgcMedia($this->model()))->canSee(function ($request) {
                if ($this->user_id) {
                    return auth()->user()->id == $this->user_id && $this->validated === ValidatedStatusEnum::NOT_VALIDATED->value;
                }

                return $request->has('resources');
            })
                ->confirmText(__('Are you sure you want to delete this image?'))
                ->confirmButtonText(__('Delete'))
                ->cancelButtonText(__('Cancel'))
                ->onlyOnDetail(),
            (new DownloadFeatureCollection())->canSee(function ($request) {
                return true;
            })
                ->canRun(function ($request) {
                    return true;
                }),
        ];
    }

    abstract public function additionalFields(Request $request);

    /**
     * Get the fields available for CSV export.
     *
     * @return array
     */
    abstract public static function getExportFields(): array;
}
