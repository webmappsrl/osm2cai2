<?php

namespace App\Traits\Nova;

use Illuminate\Http\Request;

trait UgcCommonMethodsTrait
{
    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'name',
    ];

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
     * Get the default label for the resource
     * 
     * @param string $defaultLabel
     * @return string
     */
    protected static function getResourceLabel(string $defaultLabel): string
    {
        return __($defaultLabel);
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

    /**
     * Get the default filters structure
     */
    public function filters(Request $request): array
    {
        return [
            ...$this->getCommonFilters(),
            ...parent::filters($request),
        ];
    }

    /**
     * Get the default actions structure
     */
    public function actions(Request $request): array
    {
        $parentActions = parent::actions($request);
        $commonActions = $this->getCommonActions($request, $this->getPermissionType());

        return array_merge($parentActions, $commonActions);
    }

    /**
     * Get the export fields with base common fields
     */
    public static function getExportFields(): array
    {
        return array_merge(parent::getExportFields(), static::getAdditionalExportFields());
    }

    /**
     * Get permission type for actions - to be implemented by each class
     */
    abstract protected function getPermissionType(): string;

    /**
     * Get additional export fields specific to each resource type
     */
    protected static function getAdditionalExportFields(): array
    {
        return [];
    }
} 