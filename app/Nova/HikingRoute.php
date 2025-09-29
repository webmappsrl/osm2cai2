<?php

namespace App\Nova;

use App\Models\EcPoi;
use App\Models\HikingRoute as HikingRouteModel;
use App\Models\User;
use App\Nova\Actions\AddRegionFavoritePublicationDateToHikingRouteAction;
use App\Nova\Actions\CacheMiturApi;
use App\Nova\Actions\CreateIssue;
use App\Nova\Actions\DeleteHikingRouteAction;
use App\Nova\Actions\ImportPois;
use App\Nova\Actions\ManageHikingRouteValidationAction;
use App\Nova\Actions\OsmSyncHikingRouteAction;
use App\Nova\Actions\OverpassMap;
use App\Nova\Actions\PercorsoFavoritoAction;
use App\Nova\Actions\SectorRefactoring;
use App\Nova\Actions\UploadValidationRawDataAction;
use App\Nova\Cards\LinksCard;
use App\Nova\Cards\Osm2caiStatusCard;
use App\Nova\Cards\RefCard;
use App\Nova\Fields\FeatureCollectionMap\src\FeatureCollectionMap;
use App\Nova\Filters\AreaFilter;
use App\Nova\Filters\CaiHutsHRFilter;
use App\Nova\Filters\CorrectGeometryFilter;
use App\Nova\Filters\DeletedOnOsmFilter;
use App\Nova\Filters\IssueStatusFilter;
use App\Nova\Filters\ProvinceFilter;
use App\Nova\Filters\RegionFavoriteHikingRouteFilter;
use App\Nova\Filters\RegionFilter;
use App\Nova\Filters\ScoreFilter;
use App\Nova\Filters\SDAFilter;
use App\Nova\Filters\SectorFilter;
use App\Nova\Lenses\HikingRoutesStatus0Lens;
use App\Nova\Lenses\HikingRoutesStatus1Lens;
use App\Nova\Lenses\HikingRoutesStatus2Lens;
use App\Nova\Lenses\HikingRoutesStatus3Lens;
use App\Nova\Lenses\HikingRoutesStatus4Lens;
use Ebess\AdvancedNovaMediaLibrary\Fields\Images;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Tabs\Tab;

class HikingRoute extends OsmfeaturesResource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<HikingRouteModel>
     */
    public static $model = HikingRouteModel::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'osmfeatures_data->properties->ref',
        'osmfeatures_data->properties->ref_REI',
        'osmfeatures_data->properties->osm_id',
    ];

    public function title()
    {
        $supplementaryString = ' - ';

        if (is_string($this->osmfeatures_data)) {
            $osmfeatures_data = json_decode($this->osmfeatures_data, true);
        } else {
            $osmfeatures_data = $this->osmfeatures_data;
        }

        if (! empty($osmfeatures_data['properties']['name'])) {
            $supplementaryString .= $osmfeatures_data['properties']['name'];
        }

        if (! empty($osmfeatures_data['properties']['ref'])) {
            $supplementaryString .= ' ref: '.$osmfeatures_data['properties']['ref'];
        }

        if ($this->sectors->count()) {
            $supplementaryString .= ' ('.$this->sectors->pluck('name')->implode(', ').')';
        }

        return $this->id.$supplementaryString;
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        $query->with([
            'regions',
            'provinces',
            'areas',
            'sectors', // Eager load sectors
        ]);

        // Filtra solo le hiking routes con app_id = 1
        $query->where('app_id', 1);

        if (auth()->user()->getTerritorialRole() == 'regional') {
            return $query->whereHas('regions', function (Builder $q) {
                $q->where('regions.id', auth()->user()->region->id);
            });
        }

        return $query;
    }

    /**
     * Get the fields displayed by the resource.
     */
    public function fields(NovaRequest $request): array
    {
        // Get fields from parent
        $osmfeaturesFields = parent::fields($request);

        // Filter unwanted fields
        $filteredFields = array_filter($osmfeaturesFields, function ($field) {
            return ! in_array($field->attribute, ['id', 'name', 'osmfeatures_data', 'created_at', 'updated_at', 'osmfeatures_updated_at']);
        });

        // Define desired field order
        $order = [
            'Osmfeatures ID' => __('Osmfeatures ID'),
            'percorribilita' => __('Accessibility'),
            'correttezza_geometria' => __('Geometry Correctness'),
            'coerenza_ref_rei' => __('REI Ref Consistency'),
            'geometry_sync' => __('Geometry Sync'),
            'legenda' => __('Legend'),
            'geometry' => __('Geometry'),
        ];

        $specificFields = array_merge($this->getIndexFields(), $this->getDetailFields());

        $allFields = array_merge($filteredFields, $specificFields);

        $allFieldsAssoc = [];
        foreach ($allFields as $field) {
            $key = $field->name ?? $field->attribute;
            $allFieldsAssoc[$key] = $field;
        }

        uasort($allFieldsAssoc, function ($a, $b) use ($order) {
            $aKey = $a->name ?? $a->attribute;
            $bKey = $b->name ?? $b->attribute;
            $aIndex = array_search($aKey, array_keys($order));
            $bIndex = array_search($bKey, array_keys($order));

            return $aIndex <=> $bIndex;
        });

        // Convert back to indexed array
        $orderedFields = array_values($allFieldsAssoc);

        return array_merge($orderedFields, [
            Boolean::make(__('Geometry Correctness'), 'is_geometry_correct')->onlyOnDetail(),
            Boolean::make(__('REI Ref Consistency'), function () {
                return $this->ref_rei == $this->ref_rei_comp;
            })->onlyOnDetail(),
            Boolean::make(__('Geometry Sync'), 'geometry_sync')->onlyOnDetail(),

            Tab::group(__('Details'), [
                Tab::make(__('Main'), $this->getMainTabFields()),
                Tab::make(__('General'), $this->getGeneralTabFields()),
                Tab::make(__('Tech'), $this->getTechTabFields()),
                Tab::make(__('Other'), $this->getOtherTabFields()),
                Tab::make(__('Content'), $this->getContentTabFields()),
                Tab::make(__('Issues'), $this->getIssuesTabFields()),
                Tab::make(__('POI'), $this->getPOITabFields($request)),
                Tab::make(__('Huts'), $this->getHutsTabFields($request)),
                Tab::make(__('Natural Springs'), $this->getNaturalSpringsTabFields($request)),
            ]),
        ]);
    }

    /**
     * Get the cards available for the request.
     */
    public function cards(NovaRequest $request): array
    {
        // Check if resource ID is present in request
        if ($request->resourceId) {
            // Access current model via resource ID
            $hr = HikingRouteModel::find($request->resourceId);
            $linksCardData = $hr->getDataForNovaLinksCard();

            return [
                (new RefCard($hr))->onlyOnDetail(),
                (new LinksCard($linksCardData))->onlyOnDetail(),
                (new Osm2caiStatusCard($hr))->onlyOnDetail(),
            ];
        }

        // Return empty array if not in detail or no data
        return [];
    }

    /**
     * Get the filters available for the resource.
     */
    public function filters(NovaRequest $request): array
    {
        $regionalReferentFilters = [
            (new ProvinceFilter),
            (new AreaFilter),
            (new SectorFilter),
            (new DeletedOnOsmFilter),
            (new RegionFavoriteHikingRouteFilter),
        ];

        if (auth()->user()->hasRole('Regional Referent')) {
            return $regionalReferentFilters;
        }

        $parentFilters = parent::filters($request);
        // remove App\Nova\Filters\ScoreFilter from $parentFilters array
        foreach ($parentFilters as $key => $filter) {
            if ($filter instanceof ScoreFilter) {
                unset($parentFilters[$key]);
            }
        }
        $specificFilters = [
            (new RegionFilter),
            (new ProvinceFilter),
            (new AreaFilter),
            (new SectorFilter),
            (new DeletedOnOsmFilter),
            (new RegionFavoriteHikingRouteFilter),
            (new IssueStatusFilter),
            (new CorrectGeometryFilter),
            (new CaiHutsHRFilter),
            (new SDAFilter),
        ];
        $filters = array_merge($parentFilters, $specificFilters);

        return $filters;
    }

    /**
     * Get the lenses available for the resource.
     */
    public function lenses(NovaRequest $request): array
    {
        return [
            (new HikingRoutesStatus0Lens),
            (new HikingRoutesStatus1Lens),
            (new HikingRoutesStatus2Lens),
            (new HikingRoutesStatus3Lens),
            (new HikingRoutesStatus4Lens),
        ];
    }

    /**
     * Get the actions available for the resource.
     */
    public function actions(NovaRequest $request): array
    {
        return [
            (new UploadValidationRawDataAction)
                ->confirmButtonText(__('Upload'))
                ->cancelButtonText(__('Do not upload'))
                ->canSee(function ($request) {
                    return true;
                })
                ->canRun(
                    function ($request, $user) {
                        return true;
                    }
                ),
            (new ManageHikingRouteValidationAction)
                ->confirmText(ManageHikingRouteValidationAction::getValidationConfirmText($this->model()))
                ->confirmButtonText(ManageHikingRouteValidationAction::getValidationButtonText($this->model()))
                ->cancelButtonText(__('Cancel'))
                ->canSee(function ($request) {
                    return true;
                })
                ->canRun(
                    function ($request, $user) {
                        return true;
                    }
                ),
            (new OsmSyncHikingRouteAction)
                ->confirmText(__('Are you sure you want to sync OSM data?'))
                ->confirmButtonText(__('Update with OSM data'))
                ->cancelButtonText(__('Cancel'))
                ->canSee(function ($request) {
                    return true;
                })
                ->canRun(
                    function ($request, $user) {
                        return true;
                    }
                ),
            (new DeleteHikingRouteAction)
                ->confirmText(__('Are you sure you want to delete this route?').'REF:'.$this->ref.' (REI CODE: '.$this->ref_REI.' / '.$this->ref_REI_comp.')')
                ->confirmButtonText(__('Confirm'))
                ->cancelButtonText(__('Cancel'))
                ->canSee(function ($request) {
                    return true;
                })
                ->canRun(
                    function ($request, $user) {
                        return true;
                    }
                ),
            (new SectorRefactoring)
                ->onlyOnDetail('true')
                ->confirmText(__('Are you sure you want to refactor sectors for this route?').'REF:'.$this->ref.' (REI CODE: '.$this->ref_REI.' / '.$this->ref_REI_comp.')')
                ->confirmButtonText(__('Confirm'))
                ->cancelButtonText(__('Cancel'))
                ->canSee(function ($request) {
                    return true;
                })
                ->canRun(
                    function ($request, $user) {
                        return true;
                    }
                ),
            (new CacheMiturApi('HikingRoute'))
                ->canSee(function ($request) {
                    return $request->user()->hasRole('Administrator');
                })->canRun(function ($request) {
                    return $request->user()->hasRole('Administrator');
                }),
            (new PercorsoFavoritoAction)
                ->onlyOnDetail('true')
                ->confirmText(__('Are you sure you want to update this route?'))
                ->confirmButtonText(__('Confirm'))
                ->cancelButtonText(__('Cancel'))
                ->canSee(function ($request) {
                    return true;
                })
                ->canRun(
                    function ($request, $user) {
                        return true;
                    }
                ),
            (new AddRegionFavoritePublicationDateToHikingRouteAction)
                ->onlyOnDetail('true')
                ->confirmText(__('Set expected publication date on Scarpone Online'))
                ->confirmButtonText(__('Confirm'))
                ->cancelButtonText(__('Cancel'))
                ->canSee(function ($request) {
                    return auth()->user()->hasRole('Administrator') || auth()->user()->hasRole('National Referent');
                })
                ->canRun(
                    function ($request, $user) {
                        return true;
                    }
                ),
            (new CreateIssue($this->model()))
                ->confirmText(__('Are you sure you want to create an issue for this route?'))
                ->confirmButtonText(__('Confirm'))
                ->cancelButtonText(__('Cancel'))
                ->canSee(function ($request) {
                    return auth()->user()->getTerritorialRole() != 'unknown';
                })
                ->canRun(
                    function ($request, $user) {
                        return true;
                    }
                )
                ->showInline(),
            (new OverpassMap($this->model()))
                ->onlyOnDetail('true')
                ->confirmText(__('Are you sure you want to create an Overpass map for this route?'))
                ->confirmButtonText(__('Confirm'))
                ->cancelButtonText(__('Cancel'))
                ->canSee(function ($request) {
                    $userRoles = auth()->user()->getRoleNames()->toArray();

                    // can only see if admin, itinerary manager or national referent
                    return in_array('Administrator', $userRoles) || in_array('National Referent', $userRoles) || in_array('Itinerary Manager', $userRoles);
                })
                ->canRun(function ($request, $user) {
                    return true;
                }),
            (new ImportPois($this->model()))
                ->onlyOnDetail('true')
                ->confirmText(__('Are you sure you want to import POIs for this route?'))
                ->confirmButtonText(__('Confirm'))
                ->cancelButtonText(__('Cancel'))
                ->canSee(function ($request) {
                    $userRoles = auth()->user()->getRoleNames()->toArray();

                    // can only see if admin, itinerary manager or national referent
                    return in_array('Administrator', $userRoles) || in_array('National Referent', $userRoles) || in_array('Itinerary Manager', $userRoles);
                })
                ->canRun(function ($request, $user) {
                    return true;
                }),
        ];
    }

    private function getIndexFields()
    {
        $specificFields = [
            Text::make(__('Osm2cai Status'), 'osm2cai_status')
                ->hideFromDetail(),
            Text::make(__('Regions'), function () {
                if ($this->regions->isEmpty()) {
                    return 'ND';
                }
                if ($this->regions->count() >= 2) {
                    return $this->regions->first()->name.' [...]';
                }

                return $this->regions->first()->name;
            })->onlyOnIndex(),
            Text::make(__('Provinces'), function () {
                if ($this->provinces->isEmpty()) {
                    return 'ND';
                }
                if ($this->provinces->count() >= 2) {
                    return $this->provinces->first()->name.' [...]';
                }

                return $this->provinces->first()->name;
            })->onlyOnIndex(),
            Text::make(__('Areas'), function () {
                if ($this->areas->isEmpty()) {
                    return 'ND';
                }
                if ($this->areas->count() >= 2) {
                    return $this->areas->first()->name.' [...]';
                }

                return $this->areas->first()->name;
            })->onlyOnIndex(),
            Text::make(__('Sectors'), function () {
                if ($this->sectors->isEmpty()) {
                    return 'ND';
                }
                if ($this->sectors->count() >= 2) {
                    return $this->sectors->first()->name.' [...]';
                }

                return $this->sectors->first()->name;
            })->onlyOnIndex(),
            Text::make(__('REF'), 'osmfeatures_data->properties->ref')->onlyOnIndex()->sortable(),
            Text::make(__('REI Code'), 'ref_rei')->hideFromDetail(),
            Text::make(__('Accessibility'), 'issues_status')->hideFromDetail(),
            Text::make(__('Last Survey'), 'osmfeatures_data->properties->survey_date')->hideFromDetail(),
        ];

        return $specificFields;
    }

    private function getDetailFields()
    {
        $fields = [
            Text::make(__('OSM ID'), 'osmfeatures_data->properties->osm_id')->onlyOnDetail(),
            Text::make(__('Legend'), function () {
                return <<<'HTML'
                    <ul>
                        <li>Blue line: OSM2CAI/OSM path</li>
                        <li>Red line: user uploaded path</li>
                    </ul>
                    HTML;
            })->asHtml()->onlyOnDetail(),
            FeatureCollectionMap::make(__('geometry')),
        ];

        return $fields;
    }

    private function getPolesList(Collection|array $poles)
    {
        $list = [];
        foreach ($poles as $pole) {
            $url = "/resources/poles/{$pole->id}";
            $name = $pole->ref;
            $list[] = "<li><a href='{$url}' target='_blank' class='text-primary'>{$name}</a></li>";
        }

        return implode('', $list);
    }

    private function createField($label, $infomont, $osmPath, $modelAttribute = null, $isLink = false, $withCalculated = false)
    {
        return Text::make(__($label), function () use ($infomont, $osmPath, $modelAttribute, $isLink, $withCalculated) {
            $osmValue = $this->getOsmValue($osmPath);

            if ($isLink && $osmValue) {
                $osmValue = "<a style='color:blue;' href='{$osmValue}' target='_blank'>{$osmValue}</a>";
            }

            $infomontValue = $modelAttribute ? $this->$modelAttribute : $this->$infomont;

            $html = "<p>INFOMONT: {$infomontValue}</p><p>OSM: {$osmValue}</p>";

            if ($withCalculated) {
                $calculated = $this->getOsmValue(str_replace('properties', 'properties.dem_enrichment', $osmPath));
                $html .= "<p>CALCULATED VALUE: {$calculated}</p>";
            }

            return $html;
        })->onlyOnDetail()->asHtml();
    }

    private function getOsmValue($path)
    {
        $keys = explode('.', $path);
        $value = $this->osmfeatures_data;

        foreach ($keys as $key) {
            if (! isset($value[$key])) {
                return '';
            }
            $value = $value[$key];
        }

        return $value;
    }

    private function getMainTabFields()
    {
        return [
            $this->createField('Source', 'source', 'properties.source'),
            $this->createField('Survey Date', 'survey_date', 'properties.survey_date'),
            $this->createField('CAI Section Code', 'source_ref', 'properties.osm_tags.source:ref'),
            $this->createField('Previous REF', 'old_ref', 'properties.old_ref'),
            $this->createField('REF rei', 'ref_rei', 'properties.ref_REI'),
            $this->createField('Regional REF', 'reg_ref', 'properties.osm_tags.reg_ref'),
        ];
    }

    private function getGeneralTabFields()
    {
        return [
            $this->createField('Starting Location', 'from', 'properties.from'),
            $this->createField('Ending Location', 'to', 'properties.to'),
            $this->createField('Path Name', 'name', 'properties.name'),
            $this->createField('Hiking Network Type', 'type', 'properties.osm_tags.type'),
            $this->createField('OSM Sign Code', 'osmc_symbol', 'properties.osm_tags.osmc:symbol'),
            $this->createField('Sign Description (EN)', 'symbol', 'properties.osm_tags.symbol'),
            $this->createField('Sign Description (IT)', 'symbol_it', 'properties.osm_tags.symbol:it'),
            $this->createField('Round Trip', 'roundtrip', 'properties.roundtrip'),
            $this->createField('Hiking Network Name', 'rwn_name', 'properties.rwn_name'),
        ];
    }

    private function getTechTabFields()
    {
        $techFields = [
            'Length in Km' => ['distance', 'properties.distance'],
            'CAI Difficulty' => ['cai_scale', 'properties.cai_scale'],
            'Positive Elevation in meters' => ['ascent', 'properties.ascent'],
            'Negative Elevation in meters' => ['descent', 'properties.descent'],
            'Duration (S->E) in minutes' => ['duration_forward', 'properties.duration_forward'],
            'Duration (E->S) in minutes' => ['duration_backward', 'properties.duration_backward'],
            'Maximum Elevation in meters' => ['ele_max', 'properties.ele_max'],
            'Minimum Elevation in meters' => ['ele_min', 'properties.ele_min'],
            'Starting Point Elevation in meters' => ['ele_from', 'properties.ele_from'],
            'Ending Point Elevation in meters' => ['ele_to', 'properties.ele_to'],
        ];

        return array_map(function ($label, $config) {
            // if diff cai
            if ($label == 'CAI Difficulty') {
                return $this->createField($label, $config[0], $config[1], null, false, false);
            }

            return $this->createField($label, $config[0], $config[1], null, false, true);
        }, array_keys($techFields), $techFields);
    }

    private function getOtherTabFields()
    {
        $fields = [
            'Description (EN)' => ['description', 'properties.description'],
            'Description (IT)' => ['description_it', 'properties.description_it'],
            'Maintenance (EN)' => ['maintenance', 'properties.maintenance'],
            'Maintenance (IT)' => ['maintenance_it', 'properties.maintenance_it'],
            'Note (EN)' => ['note', 'properties.note'],
            'Note (IT)' => ['note_it', 'properties.note_it'],
            'Project Notes' => ['note_project_page', 'properties.note_project_page'],
            'Operator' => ['operator', 'properties.osm_tags.operator'],
            'Path Status' => ['state', 'properties.state'],
        ];

        $standardFields = array_map(function ($label, $config) {
            return $this->createField($label, $config[0], $config[1]);
        }, array_keys($fields), $fields);

        return array_merge($standardFields, [
            $this->createField('Website', 'website', 'properties.website', null, true),
            $this->createField('Wikimedia Image', 'wikimedia_commons', 'properties.wikimedia_commons', null, true),
        ]);
    }

    private function getContentTabFields()
    {
        return [
            Text::make(__('Auto matic Name (computed for TDH)'), fn () => $this->getNameForTDH()['it'])->onlyOnDetail(),
            Text::make(__('Automati c Abstract (computed for TDH)'), fn () => $this->tdh['abstract']['it'] ?? $this->tdh['abstract']['en'] ?? '')->onlyOnDetail(),
            Images::make(__('Feature Image'), 'feature_image')->onlyOnDetail(),
            Text::make(__('Description CAI IT'), 'description_cai_it')->hideFromIndex(),
        ];
    }

    private function getIssuesTabFields()
    {
        return [
            Text::make(__('Issue Status'), 'issues_status')->onlyOnDetail(),
            Textarea::make(__('Issue Description'), 'issues_description')->onlyOnDetail(),
            Date::make(__('Issue Date'), 'issues_last_update')->onlyOnDetail(),
            Text::make(__('Issue Author'), function () {
                $user = User::find($this->model()->issues_user_id);

                return $user
                    ? '<a style="color:blue;" href="'.url('/resources/users/'.$user->id).'" target="_blank">'.$user->name.'</a>'
                    : __('No User');
            })->hideFromIndex()->asHtml(),
            Code::make(__('Accessibility History'), 'issues_chronology')
                ->json()
                ->onlyOnDetail(),
        ];
    }

    private function getPOITabFields(NovaRequest $request)
    {
        if (! $request->isResourceDetailRequest()) {
            return [];
        }

        try {
            \Log::info('HikingRoute getPOITabFields() called for ID: '.($this->model()->id ?? 'N/A'));
            $pois = $this->model()->getElementsInBuffer(new EcPoi, 10000);
            \Log::info('HikingRoute getPOITabFields() found '.count($pois).' POIs');
        } catch (\Exception $e) {
            \Log::error('HikingRoute getPOITabFields() error: '.$e->getMessage(), [
                'hiking_route_id' => $this->model()->id ?? 'N/A',
                'exception' => $e,
            ]);
            $pois = collect([]); // Return empty collection on error
        }
        $fields[] = Text::make('', function () use ($pois) {
            if (count($pois) < 1) {
                return '<h2 style="color:#666; font-size:1.5em; margin:20px 0;">'.__('No POIs found within 1km radius').'</h2>';
            }

            return '<h2 style="color:#2697bc;fnt-size:1.5em; margin:20px 0;">'.__('Poit of interest within 1km radius').'</h2>';
        })->asHtml()->onlyOnDetail();

        if (count($pois) > 0) {
            $tableRows = [];
            foreach ($pois as $poi) {
                $tags = null;
                if ($poi->osmfeatures_data &&
                    isset($poi->osmfeatures_data['properties']) &&
                    isset($poi->osmfeatures_data['properties']['osm_tags'])) {
                    $tags = $poi->osmfeatures_data['properties']['osm_tags'];
                }
                $tagList = '';
                if ($tags) {
                    $tagList = '<ul style="list-style:none; padding:0; margin:0;">';
                    foreach ($tags as $key => $value) {
                        $tagList .= "<li style='padding:3px 0;'><span style='color:#666; font-weight:bold;'>{$key}:</span> {$value}</li>";
                    }
                    $tagList .= '</ul>';
                }

                // Controlli per osm_id e osm_type
                $osmId = '';
                $osmType = '';
                if ($poi->osmfeatures_data &&
                    isset($poi->osmfeatures_data['properties'])) {
                    $osmId = $poi->osmfeatures_data['properties']['osm_id'] ?? '';
                    $osmType = $poi->osmfeatures_data['properties']['osm_type'] ?? '';
                }

                $tableRows[] = "<tr style='border-bottom:1px solid #eee; transition: background 0.2s;' onmouseover=\"this.style.background='#f5f5f5'\" onmouseout=\"this.style.background='white'\">
            <td style='padding:12px; border-right:1px solid #eee;'><a style='text-decoration: none; color: #2697bc; font-weight: bold; transition: color 0.2s;' href='/resources/ec-pois/{$poi->id}' onmouseover=\"this.style.color='#1a7594'\" onmouseout=\"this.style.color='#2697bc'\">{$poi->name}</a></td>
            <td style='padding:12px; border-right:1px solid #eee;'><code style='background:#f8f8f8; padding:2px 6px; border-radius:3px;'>{$osmId}</code></td>
            <td style='padding:12px; border-right:1px solid #eee;'>{$tagList}</td>
            <td style='padding:12px; text-align:center;'><span style='background:#e3f2fd; color:#1976d2; padding:4px 8px; border-radius:4px; font-size:0.9em;'>{$osmType}</span></td>
        </tr>";
            }

            $fields[] = Text::make(__('Results'), function () use ($tableRows) {
                return "<div style='background:white; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.1); overflow:hidden; margin:10px 0;'>
                <table style='width:100%; border-collapse:collapse; background:white;'>
                    <thead>
                        <tr style='background:#f5f7fa;'>
                            <th style='padding:15px; text-align:left; color:#2697bc; font-weight:60;border-boto:2px solid #eee;'>".__('Name')."</th>
                            <th style='padding:15px; text-align:left; color:#2697bc; font-weight:60;border-botto:px solid #eee;'>".__('OSM ID')."</th>
                            <th style='padding:15px; text-align:left; color:#2697bc; font-weight:60;border-bottom:p solid #eee;'>".__('OSM Tags')."</th>
                            <th style='padding:15px; text-align:center; color:#2697bc; font-weight:60;border-bottom:p solid #eee;'>".__('OSM Type').'</th>
                        </tr>
                    </had>
                   <body>'.implode('', $tableRows).'</tbody>
                </table>
                </div>';
            })->asHtml()->onlyOnDetail();
        }

        return $fields;
    }

    private function getHutsTabFields(NovaRequest $request)
    {
        if (! $request->isResourceDetailRequest()) {
            return [];
        }

        $huts = $this->model()->nearbyCaiHuts;

        if (empty($huts)) {
            return [
                Text::make('', fn () => '<h2 style="color:#666;fnt-size:1.5em; margi:0px 0;">'.__('No huts nearby').'</h2>')->asHtml()->onlyOnDetail(),
            ];
        }
        $fields = [
            Text::make('', function () {
                return '<h2 style="color:#2697bc;fnt-size:1.5em; magn:20px 0;">'.__('Nearby Huts').'</h2>';
            })->asHtml()->onlyOnDetail(),
        ];

        $tableRows = [];

        foreach ($huts as $hut) {
            $tableRows[] = "<tr style='border-bottom:1px solid #eee; transition: background 0.2s;' onmouseover=\"this.style.background='#f5f5f5'\" onmouseout=\"this.style.background='white'\">
                <td style='padding:12px; border-right:1px solid #eee;'><code style='background:#f8f8f8; padding:2px 6px; border-radius:3px;'>{$hut->id}</code></td>
                <td style='padding:12px; border-right:1px solid #eee;'><a style='text-decoration: none; color: #2697bc; font-weight: bold; transition: color 0.2s;' href='/resources/cai-huts/{$hut->id}' onmouseover=\"this.style.color='#1a7594'\" onmouseout=\"this.style.color='#2697bc'\">{$hut->name}</a></td>
            </tr>";
        }

        $fields[] = Text::make(__('Results'), function () use ($tableRows) {
            return "<div style='background:white; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.1); overflow:hidden; margin:10px 0;'>
                <table style='width:100%; border-collapse:collapse; background:white;'>
                    <thead>
                        <tr style='background:#f5f7fa;'>
                            <th style='padding:15px; text-align:left; color:#2697bc; font-weight:60;border-btom:2px solid #eee;'>".__('ID')."</th>
                            <th style='padding:15px; text-align:left; color:#2697bc; font-weight:60;border-boto:2px solid #eee;'>".__('Name').'</th>
                        </tr>
                    </had>
                   <body>'.implode('', $tableRows).'</tbody>
                </table>
            </div>';
        })->asHtml()->onlyOnDetail();

        return $fields;
    }

    private function getNaturalSpringsTabFields(NovaRequest $request)
    {
        if (! $request->isResourceDetailRequest()) {
            return [];
        }

        $naturalSprings = $this->model()->nearbyNaturalSprings;

        if (empty($naturalSprings)) {
            return [
                Text::make('', fn () => '<h2 style="color:#666; font-size:1.5em; margin:20px 0;">'.__('No natural springs nearby').'</h2>')->asHtml()->onlyOnDetail(),
            ];
        }

        $fields = [
            Text::make('', function () {
                return '<h2 style="color:#2697bc;fnt-size:1.5em; margin:20px 0"'.__('Nearby Natural Springs').'</h2>';
            })->asHtml()->onlyOnDetail(),
        ];

        $tableRows = [];

        foreach ($naturalSprings as $naturalSpring) {
            $tableRows[] = "<tr style='border-bottom:1px solid #eee; transition: background 0.2s;' onmouseover=\"this.style.background='#f5f5f5'\" onmouseout=\"this.style.background='white'\">
                <td style='padding:12px; border-right:1px solid #eee;'><code style='background:#f8f8f8; padding:2px 6px; border-radius:3px;'>{$naturalSpring->id}</code></td>
                <td style='padding:12px; border-right:1px solid #eee;'><a style='text-decoration: none; color: #2697bc; font-weight: bold; transition: color 0.2s;' href='/resources/natural-springs/{$naturalSpring->id}' onmouseover=\"this.style.color='#1a7594'\" onmouseout=\"this.style.color='#2697bc'\">{$naturalSpring->name}</a></td>
            </tr>";
        }

        $fields[] = Text::make(__('Results'), function () use ($tableRows) {
            return "<div style='background:white; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.1); overflow:hidden; margin:10px 0;'>
                <table style='width:100%; border-collapse:collapse; background:white;'>
                    <thead>
                        <tr style='background:#f5f7fa;'>
                            <th style='padding:15px; text-align:left; color:#2697bc; font-weight:60;border-btom:2px solid #eee;'>".__('ID')."</th>
                            <th style='padding:15px; text-align:left; color:#2697bc; font-weight:60;border-boto:2px solid #eee;'>".__('Name').'</th>
                        </tr>
                    </had>
                   <body>'.implode('', $tableRows).'</tbody>
                </table>
            </div>';
        })->asHtml()->onlyOnDetail();

        return $fields;
    }

    public function authorizedToDelete($request)
    {
        return $request instanceof ActionRequest;
    }

    public function authorizedToForceDelete($request)
    {
        return $request instanceof ActionRequest;
    }
}
