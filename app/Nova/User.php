<?php

namespace App\Nova;

use App\Nova\Club;
use Illuminate\Validation\Rules;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\Gravatar;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Spatie\Permission\Models\Permission;
use Vyuldashev\NovaPermission\PermissionBooleanGroup;
use Vyuldashev\NovaPermission\RoleBooleanGroup;
use Wm\WmPackage\Nova\AbstractUser;

class User extends AbstractUser
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\User::class;



    /**
     * Get the fields displayed by the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $baseFields = parent::fields($request);

        $relationFields = [
            BelongsToMany::make('Provinces', 'provinces', Province::class)
                ->searchable()
                ->sortable(),

            BelongsToMany::make('Areas', 'areas', Area::class)
                ->searchable()
                ->sortable(),

            BelongsToMany::make('Sectors', 'sectors', Sector::class)
                ->searchable()
                ->sortable(),

            BelongsTo::make('Region', 'region', Region::class)
                ->searchable()
                ->nullable()
                ->sortable(),

            BelongsTo::make('Club', 'club', Club::class)
                ->searchable()
                ->nullable()
                ->sortable(),

        ];
        return [
            ...array_slice($baseFields, 0, 5),
            ...$relationFields,
            ...array_slice($baseFields, 4)
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }
}
