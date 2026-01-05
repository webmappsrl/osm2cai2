<?php

namespace App\Nova;

use App\Nova\Filters\UgcFormIdFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Nova\Fields\Text;

abstract class AbstractValidationResource extends UgcPoi
{
    abstract public static function getFormId(): string;

    abstract public static function getLabel(): string;

    public static function label()
    {
        return __(static::getLabel());
    }

    public static function indexQuery(Request $request, $query)
    {
        return $query->where('form_id', static::getFormId())->orWhere('properties->form->id', static::getFormId());
    }

    public function fields(Request $request): array
    {
        $parentFields = parent::fields($request);

        $fields = collect($parentFields)->reject(function ($field) {
            return $field instanceof Text && $field->attribute === 'form_id';
        })->toArray();

        return $fields;
    }

    public function filters(Request $request): array
    {
        $parentFilters = parent::filters($request);
        $parentFilters = collect($parentFilters)->reject(function ($filter) {
            return $filter instanceof UgcFormIdFilter;
        })->toArray();

        return $parentFilters;
    }

    public function authorizeToView(Request $request)
    {
        return optional(Auth::user())->isValidatorForFormId(static::getFormId());
    }

    public function authorizeToViewAny(Request $request)
    {
        return optional(Auth::user())->isValidatorForFormId(static::getFormId());
    }

    public static function availableForNavigation(Request $request)
    {
        return optional(Auth::user())->isValidatorForFormId(static::getFormId());
    }

    public function authorizedToUpdate(Request $request)
    {
        return optional(Auth::user())->isValidatorForFormId(static::getFormId());
    }

    public static function authorizedToCreate(Request $request)
    {
        return false;
    }
}
