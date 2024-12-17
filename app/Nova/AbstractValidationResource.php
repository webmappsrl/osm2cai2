<?php

namespace App\Nova;

use App\Nova\Filters\UgcFormIdFilter;
use App\Nova\Filters\UgcUserNoMatchFilter;
use Illuminate\Http\Request;
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
        return $query->where('form_id', static::getFormId());
    }

    public function fields(Request $request)
    {
        $parentFields = parent::fields($request);

        $fields = collect($parentFields)->reject(function ($field) {
            return $field instanceof Text && $field->attribute === 'form_id';
        })->toArray();

        return $fields;
    }

    public function filters(Request $request)
    {
        $parentFilters = parent::filters($request);
        $parentFilters = collect($parentFilters)->reject(function ($filter) {
            return $filter instanceof UgcFormIdFilter || $filter instanceof UgcUserNoMatchFilter;
        })->toArray();

        return $parentFilters;
    }

    public function authorizeToView(Request $request)
    {
        return auth()->user()->isValidatorForFormId(static::getFormId());
    }

    public function authorizeToViewAny(Request $request)
    {
        return auth()->user()->isValidatorForFormId(static::getFormId());
    }

    public static function availableForNavigation(Request $request)
    {
        return auth()->user()->isValidatorForFormId(static::getFormId());
    }

    public function authorizedToUpdate(Request $request)
    {
        return auth()->user()->isValidatorForFormId(static::getFormId());
    }

    public static function authorizedToCreate(Request $request)
    {
        return false;
    }
}
