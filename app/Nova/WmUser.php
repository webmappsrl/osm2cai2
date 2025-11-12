<?php

namespace App\Nova;

use App\Models\User as UserModel;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;
use Wm\WmPackage\Nova\AbstractUserResource;

class WmUser extends AbstractUserResource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = UserModel::class;

    public static function label(): string
    {
        return __('Users');
    }

    public static function singularLabel(): string
    {
        return __('User');
    }

    public static function indexQuery(NovaRequest $request, $query): Builder
    {
        // Show only users with mobile-created UGC
        $query->where(function ($q) {
            $q->has('ugcPois')->orHas('ugcTracks');
        });

        return $query;
    }
}
