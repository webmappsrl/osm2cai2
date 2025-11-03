<?php

namespace App\Nova;

use App\Models\Region;
use App\Models\User as UserModel;
use App\Nova\Filters\AreaFilter;
use App\Nova\Filters\ProvinceFilter;
use App\Nova\Filters\RegionFilter;
use App\Nova\Filters\SectorFilter;
use App\Nova\Filters\UserTypeFilter;
use App\Nova\Region as NovaRegion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Wm\WmPackage\Nova\AbstractUserResource;

class MobileUser extends AbstractUserResource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = UserModel::class;

    /**
     * Get the displayable label of the resource.
     *
     * @return string
     */
    public static function label()
    {
        return __('Mobile Users');
    }

    /**
     * Get the displayable singular label of the resource.
     *
     * @return string
     */
    public static function singularLabel()
    {
        return __('Mobile User');
    }

    private static array $indexDefaultOrder = [
        'name' => 'asc',
    ];

    public static function indexQuery(NovaRequest $request, $query): Builder
    {
        if (empty($request->get('orderBy'))) {
            $query->getQuery()->orders = [];
            $query->orderBy(key(static::$indexDefaultOrder), reset(static::$indexDefaultOrder));
        }

        // Show only users with mobile-created UGC
        $query->where(function ($query) {
            $query->whereHas('ugcPois', function ($q) {
                $q->where('created_by', 'device');
            })->orWhereHas('ugcTracks', function ($q) {
                $q->where('created_by', 'device');
            });
        });

        /**
         * @var UserModel
         */
        $user = Auth::user();

        // if user is administrator or national referent
        if ($user->hasRole('Administrator') || $user->hasRole('National Referent')) {
            return $query;
        }

        // if user is regional referent
        if ($user->getTerritorialRole() == 'regional') {
            $regionId = $user->region_id;

            // Get users from territorial hierarchy
            $provinces = Region::find($regionId)->provinces()->get();
            $regionUsers = UserModel::where('region_id', $regionId)->get()->pluck('id')->toArray();
            $provinceUsers = [];
            $areaUsers = [];
            $sectorUsers = [];

            foreach ($provinces as $province) {
                $provinceUsers = array_merge($provinceUsers, $province->users()->get()->pluck('id')->toArray());
                $areas = $province->areas()->get();
                foreach ($areas as $area) {
                    $areaUsers = array_merge($areaUsers, $area->users()->get()->pluck('id')->toArray());
                    $sectors = $area->sectors()->get();
                    foreach ($sectors as $sector) {
                        $sectorUsers = array_merge($sectorUsers, $sector->moderators()->get()->pluck('id')->toArray());
                    }
                }
            }

            // Get users from clubs in the region
            $clubUsers = UserModel::whereHas('club', function ($query) use ($regionId) {
                $query->whereHas('region', function ($query) use ($regionId) {
                    $query->where('id', $regionId);
                });
            })->pluck('id')->toArray();

            // Get users who manage clubs in the region
            $clubManagerUsers = UserModel::whereHas('managedClub', function ($query) use ($regionId) {
                $query->whereHas('region', function ($query) use ($regionId) {
                    $query->where('id', $regionId);
                });
            })->pluck('id')->toArray();

            // Merge all user IDs and remove duplicates
            $allUsers = array_unique(array_merge(
                $regionUsers,
                $provinceUsers,
                $areaUsers,
                $sectorUsers,
                $clubUsers,
                $clubManagerUsers
            ));

            $query->whereIn('id', $allUsers);
        }
        // if user is section manager
        elseif ($user->managedClub) {
            $clubId = $user->managedClub->id;

            // Get users who are members of the managed club
            $clubMemberIds = UserModel::whereHas('club', function ($query) use ($clubId) {
                $query->where('id', $clubId);
            })->pluck('id')->toArray();

            // Include also the club manager in the list
            $allUsers = array_unique(array_merge([$user->id], $clubMemberIds));

            $query->whereIn('id', $allUsers);
        }

        return $query;
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $baseFields = parent::fields($request);

        $relationFields = [
            Text::make(__('Phone'), 'phone')
                ->sortable()
                ->rules('max:255'),

            BelongsToMany::make(__('Provinces'), 'provinces', Province::class),

            Text::make(__('Provinces'), function () {
                return $this->provinces->pluck('name')->join(', ');
            })->onlyOnIndex(),

            BelongsToMany::make(__('Areas'), 'areas', Area::class),

            Text::make(__('Areas'), function () {
                return $this->areas->pluck('name')->join(', ');
            })->onlyOnIndex(),

            BelongsToMany::make(__('Sectors'), 'sectors', Sector::class),

            Text::make(__('Sectors'), function () {
                return $this->sectors->pluck('name')->join(', ');
            })->onlyOnIndex(),

            BelongsTo::make(__('Region'), 'region', NovaRegion::class)
                ->searchable()
                ->nullable()
                ->sortable(),

            BelongsTo::make(__('Club Member'), 'club', Club::class)
                ->searchable()
                ->nullable()
                ->sortable()
                ->hideFromIndex(),

            BelongsTo::make(__('Managed Club'), 'managedClub', Club::class)
                ->searchable()
                ->nullable()
                ->sortable(),

            HasMany::make(__('UGC POIs'), 'ugcPois', UgcPoi::class),

            HasMany::make(__('UGC Tracks'), 'ugcTracks', UgcTrack::class),

        ];

        return [
            ...array_slice($baseFields, 2, 2),
            ...$relationFields,
            ...array_slice($baseFields, 4),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     */
    public function filters(NovaRequest $request): array
    {
        return [
            new UserTypeFilter,
            new RegionFilter,
            new AreaFilter,
            new ProvinceFilter,
            new SectorFilter,
        ];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }
}
