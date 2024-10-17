<?php

namespace App\Nova;

use App\Models\UgcTrack;
use Laravel\Nova\Resource;
use Laravel\Nova\Fields\ID;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use App\Nova\Filters\DateFilter;
use Laravel\Nova\Fields\DateTime;
use App\Enums\ValidatedStatusEnum;
use Laravel\Nova\Fields\BelongsTo;
use App\Nova\Filters\UgcAppIdFilter;
use App\Nova\Filters\ValidatedFilter;
use App\Nova\Filters\RelatedUGCFilter;
use App\Traits\Nova\WmNovaFieldsTrait;
use Idez\DateRangeFilter\Enums\Config;
use App\Traits\Nova\RawDataFieldsTrait;
use Idez\DateRangeFilter\DateRangeFilter;

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
     * Get the fields displayed by the resource.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function fields(Request $request)
    {
        $novaFields = [
            ID::make(__('ID'), 'id')->sortable()->readonly(),
            Text::make('User', function () {
                if ($this->user_id) {
                    return '<a style="text-decoration:none; font-weight:bold; color:teal;" href="/resources/users/' . $this->user_id . '">' . $this->user->name . '</a>';
                } else {
                    return $this->user_no_match ?? 'N/A';
                }
            })->asHtml(),
            BelongsTo::make('User', 'user', User::class)
                ->searchable()
                ->hideWhenUpdating()
                ->hideWhenCreating()
                ->hideFromIndex()
                ->hideFromDetail(),
            Select::make('Validated', 'validated')
                ->options(ValidatedStatusEnum::cases())
                ->default(ValidatedStatusEnum::NOT_VALIDATED)
                ->canSee(function ($request) {
                    //if is an ugcTrack instance return $user->ugc_track_validator
                    if ($this instanceof UgcTrack) {
                        return $request->user()->ugc_track_validator ?? false;
                    } else
                        //handle different form_id for ugcPoi
                        return $request->user()->isValidatorForFormId($this->form_id) ?? false;
                })->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                    $isValidated = $request->$requestAttribute;
                    $model->$attribute = $isValidated;
                    // logic to track validator and validation date

                    if ($isValidated == ValidatedStatusEnum::VALID) {
                        $model->validator_id = $request->user()->id;
                        $model->validation_date = now();
                    } else {
                        $model->validator_id = null;
                        $model->validation_date = null;
                    }
                })->onlyOnForms(),
            Text::make('Validation Status', function () {
                return $this->validated;
            }),
            DateTime::make('Validation Date', 'validation_date')
                ->onlyOnDetail(),
            Text::make('Validator', function () {
                if ($this->validator_id) {
                    return $this->validator->name;
                } else {
                    return null;
                }
            })->onlyOnDetail(),
            Text::make('App ID', 'app_id')
                ->onlyOnDetail(),
            DateTime::make('Registered At', 'registered_at')
                ->readonly(),
            DateTime::make('Updated At')
                ->hideWhenCreating()
                ->hideWhenUpdating()
                ->sortable(),
            Text::make('Geohub ID', 'geohub_id')
                ->onlyOnDetail(),
            Text::make('Gallery', function () {
                $images = $this->ugc_media;
                if (empty($images)) {
                    return 'N/A';
                }
                $html = '<div style="display: flex; flex-wrap: wrap;">';
                foreach ($images as $image) {
                    $url = $image->getUrl();
                    $html .= '<div style="margin: 5px; text-align: center;">';
                    $html .= '<a href="' . $url . '" target="_blank">';
                    $html .= '<img src="' . $url . '" width="100" height="100" style="object-fit: cover;">';
                    $html .= '</a>';
                    $html .= '<p style="color: lightgray;">ID: ' . $image->id . '</p>';
                    $html .= '</div>';
                }
                $html .= '</div>';
                return $html;
            })->asHtml()->onlyOnDetail(),
        ];

        $formFields = $this->jsonForm('raw_data');

        if (!empty($formFields)) {
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
            // (new UploadAndAssociateUgcMedia())->canSee(function ($request) {
            //     if ($this->user_id)
            //         return auth()->user()->id == $this->user_id && $this->validated === ValidatedStatu::NotValidated;
            //     return $request->has('resources');
            // })
            //     ->canRun(function ($request) {
            //         return true;
            //     })
            //     ->confirmText('Sei sicuro di voler caricare questa immagine?')
            //     ->confirmButtonText('Carica')
            //     ->cancelButtonText('Annulla'),
            // (new DeleteUgcMedia($this->model()))->canSee(function ($request) {
            //     if ($this->user_id)
            //         return auth()->user()->id == $this->user_id && $this->validated === ValidatedStat::NotValidated;
            //     return $request->has('resources');
            // }),
            // (new DownloadFeatureCollection())->canSee(function ($request) {
            //     return true;
            // }),
        ];
    }

    abstract public function additionalFields(Request $request);
}
