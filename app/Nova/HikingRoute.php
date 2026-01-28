<?php

namespace App\Nova;

use App\Enums\UserRole;
use App\Models\EcPoi;
use App\Models\HikingRoute as HikingRouteModel;
use App\Models\User;
use App\Services\OsmService;
use App\Nova\Actions\AddRegionFavoritePublicationDateToHikingRouteAction;
use App\Nova\Actions\CacheMiturApi;
use App\Nova\Actions\CreateIssue;
use App\Nova\Actions\CreateTrailSurveyAction;
use App\Nova\Actions\DeleteHikingRouteAction;
use App\Nova\Actions\AddHikingRoutesToSignageProject;
use App\Nova\Actions\ExportHikingRouteSignageTo;
use App\Nova\Actions\ImportPois;
use App\Nova\Actions\ManageHikingRouteValidationAction;
use App\Nova\Actions\OverpassMap;
use App\Nova\Actions\PercorsoFavoritoAction;
use App\Nova\Actions\SectorRefactoring;
use App\Nova\Actions\UploadValidationRawDataAction;
use App\Nova\Cards\LinksCard;
use App\Nova\Cards\Osm2caiStatusCard;
use App\Nova\Cards\RefCard;
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
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\ActionRequest;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Tabs\Tab;
use Osm2cai\SignageMap\SignageMap;

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

    /**
     * The pagination per-page options used by the resource via relationships.
     *
     * @var array
     */
    public static function perPageViaRelationshipOptions()
    {
        return [25, 50, 100];
    }

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
            $supplementaryString .= ' ref: ' . $osmfeatures_data['properties']['ref'];
        }

        if ($this->sectors->count()) {
            $supplementaryString .= ' (' . $this->sectors->pluck('name')->implode(', ') . ')';
        }

        return $this->id . $supplementaryString;
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
     * Optimize search with support for ref and ref_REI fields.
     *
     * Extends parent search to include searches in ref and ref_REI JSON fields.
     * Parent already handles: osmfeatures_id (includes osm_type + osm_id), id, and name.
     * 
     * NOTE: JSON searches are slower than column searches, but ref/ref_REI are only
     * stored in JSON. Consider extracting them to separate columns for better performance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $search
     * @return \Illuminate\Database\Eloquent\Builder
     * @phpstan-ignore-next-line
     */
    protected static function applySearch(Builder $query, string $search): Builder
    {
        $searchTerm = trim($search);
        
        // Raggruppa tutte le condizioni di ricerca in una singola where clause
        // per garantire che ref e ref_REI vengano cercati correttamente insieme alle altre condizioni
        return $query->where(function ($q) use ($searchTerm, $search) {
            // Implementa la logica del parent (osmfeatures_id, id, name) direttamente qui
            // per poter raggruppare correttamente con le condizioni per ref/ref_REI
            $hasOsmPrefix = preg_match('/^([RNW])\s*(\d+)$/i', $search, $matches);
            $isNumeric = ctype_digit($search);

            if ($hasOsmPrefix) {
                // Caso 1: prefisso OSM (R/N/W + numero) - cerca solo in osmfeatures_id
                $osmId = strtoupper($matches[1]).$matches[2];
                $q->where('osmfeatures_id', 'ilike', "{$osmId}%");
            } elseif ($isNumeric) {
                // Caso 2: solo numeri - cerca in id, osmfeatures_id con prefissi R/N/W, e ref/ref_REI
                $searchInt = (int) $search;
                $q->where(function ($subQ) use ($searchInt, $search, $searchTerm) {
                    $subQ->where('id', '=', $searchInt)
                         ->orWhere('osmfeatures_id', 'ilike', "R{$search}%")
                         ->orWhere('osmfeatures_id', 'ilike', "N{$search}%")
                         ->orWhere('osmfeatures_id', 'ilike', "W{$search}%")
                         ->orWhereRaw("osmfeatures_data->'properties'->>'ref' ILIKE ?", ["%{$searchTerm}%"])
                         ->orWhereRaw("osmfeatures_data->'properties'->>'ref_REI' ILIKE ?", ["%{$searchTerm}%"]);
                });
            } else {
                // Caso 3: testo libero - cerca in name, ref e ref_REI
                $q->where('name', 'ilike', "%{$searchTerm}%")
                  ->orWhereRaw("osmfeatures_data->'properties'->>'ref' ILIKE ?", ["%{$searchTerm}%"])
                  ->orWhereRaw("osmfeatures_data->'properties'->>'ref_REI' ILIKE ?", ["%{$searchTerm}%"]);
            }
        });
    }

    /**
     * Get the fields displayed by the resource.
     */
    public function fields(NovaRequest $request): array
    {
        // Esegui operazioni sul modello prima del rendering (solo per la vista detail)
        if ($request->isResourceDetailRequest() && $this->model()) {
            $this->prepareModelForDetailView($request);
        }

        // Get fields from parent
        $osmfeaturesFields = parent::fields($request);

        // Filter unwanted fields (including geometry which is replaced by SignageMap)
        $filteredFields = array_filter($osmfeaturesFields, function ($field) {
            return ! in_array($field->attribute, ['id', 'name', 'osmfeatures_data', 'created_at', 'updated_at', 'osmfeatures_updated_at', 'geometry']);
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

        if (auth()->user()->hasRole(UserRole::RegionalReferent)) {
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
            (new DeleteHikingRouteAction)
                ->confirmText(__('Are you sure you want to delete this route?') . 'REF:' . $this->ref . ' (REI CODE: ' . $this->ref_REI . ' / ' . $this->ref_REI_comp . ')')
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
                ->confirmText(__('Are you sure you want to refactor sectors for this route?') . 'REF:' . $this->ref . ' (REI CODE: ' . $this->ref_REI . ' / ' . $this->ref_REI_comp . ')')
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
                    return $request->user()->hasRole(UserRole::Administrator);
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
                    return auth()->user()->hasRole(UserRole::Administrator) || auth()->user()->hasRole(UserRole::NationalReferent);
                }),
            (new CreateIssue($this->model()))
                ->confirmText(__('Are you sure you want to create an issue for this route?'))
                ->confirmButtonText(__('Confirm'))
                ->cancelButtonText(__('Cancel'))
                ->canSee(function ($request) {
                    return auth()->user()->getTerritorialRole() != 'unknown';
                })
                ->showInline(),
            (new OverpassMap($this->model()))
                ->onlyOnDetail('true')
                ->confirmText(__('Are you sure you want to create an Overpass map for this route?'))
                ->confirmButtonText(__('Confirm'))
                ->cancelButtonText(__('Cancel'))
                ->canSee(function ($request) {
                    $user = $request->user();

                    // can only see if admin, itinerary manager or national referent
                    return $user->hasRole(UserRole::Administrator) || $user->hasRole(UserRole::NationalReferent) || $user->hasRole(UserRole::ItineraryManager);
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
                    $user = $request->user();

                    // can only see if admin, itinerary manager or national referent
                    return $user->hasRole(UserRole::Administrator) || $user->hasRole(UserRole::NationalReferent) || $user->hasRole(UserRole::ItineraryManager);
                })
                ->canRun(function ($request, $user) {
                    return true;
                }),
            (new CreateTrailSurveyAction($this->model()))
                ->onlyOnDetail()
                ->confirmText(__('Create Trail Survey confirmation message'))
                ->confirmButtonText(__('Confirm'))
                ->cancelButtonText(__('Cancel'))
                ->canSee(function ($request) {
                    return true;
                })
                ->canRun(function ($request, $user) {
                    return true;
                }),
            (new ExportHikingRouteSignageTo())
                ->canSee(function ($request) {
                    return true;
                })
                ->canRun(function ($request, $user) {
                    return true;
                }),
            (new AddHikingRoutesToSignageProject())
                ->canSee(function ($request) {
                    return true;
                })
                ->canRun(function ($request, $user) {
                    return true;
                }),
        ];
    }

    private function getIndexFields()
    {
        $specificFields = [
            Text::make('id', 'id'),
            Text::make(__('Osm2cai Status'), 'osm2cai_status')
                ->hideFromDetail(),
            Text::make(__('Regions'), function () {
                if ($this->regions->isEmpty()) {
                    return 'ND';
                }
                if ($this->regions->count() >= 2) {
                    return $this->regions->first()->name . ' [...]';
                }

                return $this->regions->first()->name;
            })->onlyOnIndex(),
            Text::make(__('Provinces'), function () {
                if ($this->provinces->isEmpty()) {
                    return 'ND';
                }
                if ($this->provinces->count() >= 2) {
                    return $this->provinces->first()->name . ' [...]';
                }

                return $this->provinces->first()->name;
            })->onlyOnIndex(),
            Text::make(__('Areas'), function () {
                if ($this->areas->isEmpty()) {
                    return 'ND';
                }
                if ($this->areas->count() >= 2) {
                    return $this->areas->first()->name . ' [...]';
                }

                return $this->areas->first()->name;
            })->onlyOnIndex(),
            Text::make(__('Sectors'), function () {
                if ($this->sectors->isEmpty()) {
                    return 'ND';
                }
                if ($this->sectors->count() >= 2) {
                    return $this->sectors->first()->name . ' [...]';
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
            SignageMap::make(__('Geometry'), 'geometry'),
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
            Text::make(__('Automatic Name (computed for TDH)'), fn() => $this->getNameForTDH()['it'])->onlyOnDetail(),
            Text::make(__('Automatic Abstract (computed for TDH)'), fn() => $this->tdh['abstract']['it'] ?? $this->tdh['abstract']['en'] ?? '')->onlyOnDetail(),
            Images::make(__('Feature Image'), 'feature_image')->onlyOnDetail(),
            Text::make(__('Description (IT)'), 'description_cai_it')->hideFromIndex(),
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
                    ? '<a style="color:blue;" href="' . url('/resources/users/' . $user->id) . '" target="_blank">' . $user->name . '</a>'
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
            Log::info('HikingRoute getPOITabFields() called for ID: ' . ($this->model()->id ?? 'N/A'));
            $pois = $this->model()->getElementsInBuffer(new EcPoi, 10000);
            Log::info('HikingRoute getPOITabFields() found ' . count($pois) . ' POIs');
        } catch (\Exception $e) {
            Log::error('HikingRoute getPOITabFields() error: ' . $e->getMessage(), [
                'hiking_route_id' => $this->model()->id ?? 'N/A',
                'exception' => $e,
            ]);
            $pois = collect([]); // Return empty collection on error
        }
        $fields[] = Text::make('', function () use ($pois) {
            if (count($pois) < 1) {
                return '<h2 style="color:#666; font-size:1.5em; margin:20px 0;">' . __('No POIs found within 1km radius') . '</h2>';
            }

            return '<h2 style="color:#2697bc;fnt-size:1.5em; margin:20px 0;">' . __('Poit of interest within 1km radius') . '</h2>';
        })->asHtml()->onlyOnDetail();

        if (count($pois) > 0) {
            $tableRows = [];
            foreach ($pois as $poi) {
                $tags = null;
                if (
                    $poi->osmfeatures_data &&
                    isset($poi->osmfeatures_data['properties']) &&
                    isset($poi->osmfeatures_data['properties']['osm_tags'])
                ) {
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
                if (
                    $poi->osmfeatures_data &&
                    isset($poi->osmfeatures_data['properties'])
                ) {
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
                            <th style='padding:15px; text-align:left; color:#2697bc; font-weight:60;border-boto:2px solid #eee;'>" . __('Name') . "</th>
                            <th style='padding:15px; text-align:left; color:#2697bc; font-weight:60;border-botto:px solid #eee;'>" . __('OSM ID') . "</th>
                            <th style='padding:15px; text-align:left; color:#2697bc; font-weight:60;border-bottom:p solid #eee;'>" . __('OSM Tags') . "</th>
                            <th style='padding:15px; text-align:center; color:#2697bc; font-weight:60;border-bottom:p solid #eee;'>" . __('OSM Type') . '</th>
                        </tr>
                    </had>
                   <body>' . implode('', $tableRows) . '</tbody>
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
                Text::make('', fn() => '<h2 style="color:#666;fnt-size:1.5em; margi:0px 0;">' . __('No huts nearby') . '</h2>')->asHtml()->onlyOnDetail(),
            ];
        }
        $fields = [
            Text::make('', function () {
                return '<h2 style="color:#2697bc;fnt-size:1.5em; magn:20px 0;">' . __('Nearby Huts') . '</h2>';
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
                            <th style='padding:15px; text-align:left; color:#2697bc; font-weight:60;border-btom:2px solid #eee;'>" . __('ID') . "</th>
                            <th style='padding:15px; text-align:left; color:#2697bc; font-weight:60;border-boto:2px solid #eee;'>" . __('Name') . '</th>
                        </tr>
                    </had>
                   <body>' . implode('', $tableRows) . '</tbody>
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
                Text::make('', fn() => '<h2 style="color:#666; font-size:1.5em; margin:20px 0;">' . __('No natural springs nearby') . '</h2>')->asHtml()->onlyOnDetail(),
            ];
        }

        $fields = [
            Text::make('', function () {
                return '<h2 style="color:#2697bc;fnt-size:1.5em; margin:20px 0"' . __('Nearby Natural Springs') . '</h2>';
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
                            <th style='padding:15px; text-align:left; color:#2697bc; font-weight:60;border-btom:2px solid #eee;'>" . __('ID') . "</th>
                            <th style='padding:15px; text-align:left; color:#2697bc; font-weight:60;border-boto:2px solid #eee;'>" . __('Name') . '</th>
                        </tr>
                    </had>
                   <body>' . implode('', $tableRows) . '</tbody>
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

    /**
     * Prepare the model before rendering the detail view.
     * 
     * Questo metodo viene chiamato automaticamente quando viene aperta la vista detail.
     * Controlla e aggiorna automaticamente i dati da osmfeatures e OSM se necessario.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return void
     */
    protected function prepareModelForDetailView(NovaRequest $request)
    {
        $model = $this->model();

        if (! $model) {
            Log::warning('prepareModelForDetailView: Model is null, exiting');
            return;
        }

        // Verifica se la hiking route esiste su OSM (stessa logica del Job CheckHikingRouteExistenceOnOSM)
        $this->checkAndUpdateOsmExistenceStatus($model);

        // Aggiorna sempre i dati osmfeatures se presenti
        $this->updateOsmfeaturesData($model);

        // 2. Controllo aggiornamenti da OSM (priorità 2)
        //  $this->checkAndUpdateFromOsm($model);
        // 1. Controllo aggiornamenti da osmfeatures (priorità 1)
        $this->checkAndUpdateFromOsmfeatures($model);
    }

    /**
     * Verifica se la hiking route esiste su OSM e aggiorna lo stato deleted_on_osm se necessario.
     * 
     * Controlla solo se deleted_on_osm è false o null per evitare chiamate API inutili.
     * Stessa logica del Job CheckHikingRouteExistenceOnOSM.
     *
     * @param  HikingRouteModel  $model
     * @return void
     */
    protected function checkAndUpdateOsmExistenceStatus(HikingRouteModel $model): void
    {
        // Controlla solo se deleted_on_osm è false o null per evitare chiamate API inutili
        if ($model->deleted_on_osm === false || $model->deleted_on_osm === null) {
            try {
                // Verifica se esiste l'osm_id nei dati
                $osmId = $model->osmfeatures_data['properties']['osm_id'] ?? null;

                if ($osmId) {
                    $service = OsmService::getService();

                    // Verifica se la hiking route esiste su OSM
                    if ($service->hikingRouteExists($osmId) === false) {
                        // Se non esiste, imposta deleted_on_osm a true
                        $model->deleted_on_osm = true;
                        $model->saveQuietly();
                        $model->refresh();

                        Log::info('HikingRoute marked as deleted on OSM', [
                            'hiking_route_id' => $model->id,
                            'osm_id' => $osmId,
                        ]);
                    } else {
                        Log::debug('checkAndUpdateOsmExistenceStatus: HikingRoute exists on OSM', [
                            'hiking_route_id' => $model->id,
                            'osm_id' => $osmId,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                // Log dell'errore ma non bloccare il rendering della vista
                Log::error('Error checking hiking route existence on OSM', [
                    'hiking_route_id' => $model->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            Log::debug('checkAndUpdateOsmExistenceStatus: Skipping OSM existence check (deleted_on_osm is true)', [
                'hiking_route_id' => $model->id,
                'deleted_on_osm' => $model->deleted_on_osm,
            ]);
        }
    }

    /**
     * Controlla e aggiorna il modello da osmfeatures se ci sono aggiornamenti disponibili.
     *
     * @param  HikingRouteModel  $model
     * @return void
     */
    protected function checkAndUpdateFromOsmfeatures(HikingRouteModel $model): void
    {
        try {
            if ($model->osm2cai_status > 3) {
                return;
            }
            $osmfeaturesId = $model->osmfeatures_id;
            if (! $osmfeaturesId) {
                Log::warning('checkAndUpdateFromOsmfeatures: Missing osmfeatures_id, skipping', [
                    'hiking_route_id' => $model->id,
                ]);

                return;
            }

            // Usa i dati già presenti nel modello
            if (! $model->osmfeatures_data) {
                Log::warning('checkAndUpdateFromOsmfeatures: Missing osmfeatures_data, skipping', [
                    'hiking_route_id' => $model->id,
                ]);

                return;
            }

            $osmfeaturesData = is_array($model->osmfeatures_data)
                ? $model->osmfeatures_data
                : json_decode($model->osmfeatures_data, true);

            if (! $osmfeaturesData) {
                Log::warning('checkAndUpdateFromOsmfeatures: Invalid osmfeatures_data, skipping', [
                    'hiking_route_id' => $model->id,
                ]);

                return;
            }

            // Aggiorna geometria e status se le condizioni lo permettono
            $updateData = $this->updateModelGeometryAndStatus($model, $osmfeaturesData, $osmfeaturesId);

            if (! empty($updateData)) {
                $model->updateQuietly($updateData);
                $model->refresh();
            }
        } catch (\Exception $e) {
            // Log dell'errore ma non bloccare il rendering della vista
            Log::error('checkAndUpdateFromOsmfeatures: Exception occurred', [
                'hiking_route_id' => $model->id ?? null,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Aggiorna i dati osmfeatures e il timestamp (sempre applicato).
     * Fa la chiamata API a osmfeatures per ottenere i dati freschi.
     *
     * @param  HikingRouteModel  $model
     * @return void
     */
    protected function updateOsmfeaturesData(HikingRouteModel $model): void
    {
        try {
            $osmfeaturesId = $model->osmfeatures_id;
            if (! $osmfeaturesId) {
                Log::warning('updateOsmfeaturesData: Missing osmfeatures_id, skipping', [
                    'hiking_route_id' => $model->id,
                ]);

                return;
            }

            // Chiamata API sincrona a osmfeatures
            $endpoint = HikingRouteModel::getOsmfeaturesEndpoint();
            $apiUrl = $endpoint . $osmfeaturesId;

            $response = Http::timeout(10)->get($apiUrl);

            if ($response->failed()) {
                Log::warning('updateOsmfeaturesData: Failed to fetch osmfeatures data', [
                    'hiking_route_id' => $model->id,
                    'osmfeatures_id' => $osmfeaturesId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return;
            }

            $osmfeaturesData = $response->json();
            if (! $osmfeaturesData) {
                Log::warning('updateOsmfeaturesData: Empty response from osmfeatures API', [
                    'hiking_route_id' => $model->id,
                    'osmfeatures_id' => $osmfeaturesId,
                ]);

                return;
            }

            $updateData = [];

            // Aggiorna sempre osmfeatures_data
            $updateData['osmfeatures_data'] = $osmfeaturesData;

            // Il comando UpdateHikingRoutesCommand usa $route['updated_at'] dalla lista.
            // Per il dettaglio singolo, proviamo a livello root o in properties
            $updatedAtValue = $osmfeaturesData['updated_at']
                ?? $osmfeaturesData['properties']['updated_at']
                ?? now();

            // Converti sempre in UTC prima di salvare per evitare problemi di timezone
            $updateData['osmfeatures_updated_at'] = Carbon::parse($updatedAtValue)->utc()->toDateTimeString();

            if (! empty($updateData)) {
                $model->updateQuietly($updateData);
                $model->refresh();
            }
        } catch (\Exception $e) {
            Log::error('updateOsmfeaturesData: Exception occurred', [
                'hiking_route_id' => $model->id ?? null,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Aggiorna geometria e status del modello se le condizioni lo permettono.
     *
     * @param  HikingRouteModel  $model
     * @param  array  $osmfeaturesData
     * @param  string  $osmfeaturesId
     * @return array Array con i dati da aggiornare (vuoto se le condizioni non sono soddisfatte)
     */
    protected function updateModelGeometryAndStatus(HikingRouteModel $model, array $osmfeaturesData, string $osmfeaturesId): array
    {
        $updateData = [];

        // Aggiorna geometria e status solo se osm2cai_status locale non è 4
        if ($model->osm2cai_status != 4) {
            // Aggiorna geometria se presente
            $geometryUpdate = HikingRouteModel::updateGeometry($model, $osmfeaturesData, $osmfeaturesId);
            $updateData = array_merge($updateData, $geometryUpdate);

            // Aggiorna osm2cai_status se presente e diverso dal corrente
            $incomingStatus = $osmfeaturesData['properties']['osm2cai_status'] ?? null;
            if ($incomingStatus !== null && $model->osm2cai_status !== $incomingStatus) {
                $updateData['osm2cai_status'] = $incomingStatus;
            }
        }

        return $updateData;
    }


    /**
     * Controlla e aggiorna il modello da OSM se ci sono aggiornamenti disponibili.
     *
     * @param  HikingRouteModel  $model
     * @return void
     */
    protected function checkAndUpdateFromOsm(HikingRouteModel $model): void
    {
        try {
            if ($model->osm2cai_status > 3) {
                return;
            }

            $osmId = $model->osmfeatures_data['properties']['osm_id'] ?? null;
            if (! $osmId) {
                Log::info('checkAndUpdateFromOsm: Missing osm_id, skipping OSM check', [
                    'hiking_route_id' => $model->id,
                ]);

                return;
            }

            $service = OsmService::getService();

            Log::info('checkAndUpdateFromOsm: Starting OSM check', [
                'hiking_route_id' => $model->id,
                'osm_id' => $osmId,
            ]);

            // Ottieni tag OSM attuali
            Log::debug('checkAndUpdateFromOsm: Fetching OSM tags', [
                'hiking_route_id' => $model->id,
                'osm_id' => $osmId,
            ]);
            $currentOsmTags = $service->getHikingRoute($osmId);
            if ($currentOsmTags === false) {
                Log::warning('checkAndUpdateFromOsm: Failed to fetch OSM tags', [
                    'hiking_route_id' => $model->id,
                    'osm_id' => $osmId,
                ]);

                return;
            }

            Log::debug('checkAndUpdateFromOsm: OSM tags fetched', [
                'hiking_route_id' => $model->id,
                'osm_id' => $osmId,
                'tags_count' => count($currentOsmTags),
            ]);

            // Ottieni geometria OSM attuale
            // L'URL usato è: https://hiking.waymarkedtrails.org/api/v1/details/relation/{osmId}/geometry/gpx
            $waymarkedTrailsUrl = 'https://hiking.waymarkedtrails.org/api/v1/details/relation/' . intval($osmId) . '/geometry/gpx';
            Log::debug('checkAndUpdateFromOsm: Fetching OSM geometry', [
                'hiking_route_id' => $model->id,
                'osm_id' => $osmId,
                'waymarked_trails_url' => $waymarkedTrailsUrl,
            ]);
            $currentOsmGeometry = $service->getHikingRouteGeometry($osmId);
            if ($currentOsmGeometry === false) {
                Log::warning('checkAndUpdateFromOsm: Failed to fetch OSM geometry from Waymarked Trails', [
                    'hiking_route_id' => $model->id,
                    'osm_id' => $osmId,
                    'failed_url' => $waymarkedTrailsUrl,
                    'note' => 'This could mean: 1) The relation does not exist on Waymarked Trails, 2) The API is temporarily unavailable, 3) The relation has no geometry. Will skip geometry comparison but will still check tags.',
                ]);

                // Anche se la geometria non è disponibile, possiamo ancora confrontare i tag
                // Quindi non facciamo return qui, ma saltiamo solo il confronto della geometria
                $currentOsmGeometry = null;
            }

            if ($currentOsmGeometry !== null) {
                Log::debug('checkAndUpdateFromOsm: OSM geometry fetched successfully', [
                    'hiking_route_id' => $model->id,
                    'osm_id' => $osmId,
                    'geometry_length' => strlen($currentOsmGeometry),
                ]);
            }

            // Confronta tag attuali con quelli salvati
            $savedOsmTags = $model->osmfeatures_data['properties']['osm_tags'] ?? [];
            Log::debug('checkAndUpdateFromOsm: Comparing tags', [
                'hiking_route_id' => $model->id,
                'current_tags_count' => count($currentOsmTags),
                'saved_tags_count' => count($savedOsmTags),
            ]);
            $tagsChanged = $this->compareOsmTags($currentOsmTags, $savedOsmTags);

            // Confronta geometria attuale con quella salvata (solo se disponibile)
            $geometryChanged = false;
            if ($currentOsmGeometry !== null) {
                Log::debug('checkAndUpdateFromOsm: Comparing geometry', [
                    'hiking_route_id' => $model->id,
                    'has_saved_geometry' => $model->geometry !== null,
                ]);
                $geometryChanged = $this->compareOsmGeometry($currentOsmGeometry, $model->geometry);
            } else {
                Log::debug('checkAndUpdateFromOsm: Skipping geometry comparison (not available)', [
                    'hiking_route_id' => $model->id,
                ]);
            }

            Log::info('checkAndUpdateFromOsm: Comparison results', [
                'hiking_route_id' => $model->id,
                'osm_id' => $osmId,
                'tags_changed' => $tagsChanged,
                'geometry_changed' => $geometryChanged,
            ]);

            if ($tagsChanged || $geometryChanged) {
                Log::info('checkAndUpdateFromOsm: Found OSM updates, applying sync', [
                    'hiking_route_id' => $model->id,
                    'osm_id' => $osmId,
                    'tags_changed' => $tagsChanged,
                    'geometry_changed' => $geometryChanged,
                ]);

                // Aggiorna usando OsmService
                $service->updateHikingRouteModelWithOsmData($model);

                Log::info('checkAndUpdateFromOsm: Update completed successfully', [
                    'hiking_route_id' => $model->id,
                    'osm_id' => $osmId,
                ]);
            } else {
                Log::info('checkAndUpdateFromOsm: No OSM updates available', [
                    'hiking_route_id' => $model->id,
                    'osm_id' => $osmId,
                ]);
            }
            // Ricaricare il modello dopo eventuale aggiornamento da OSM
            $model->refresh();
        } catch (\Exception $e) {
            // Log dell'errore ma non bloccare il rendering della vista
            Log::error('checkAndUpdateFromOsm: Exception occurred', [
                'hiking_route_id' => $model->id ?? null,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Confronta i tag OSM attuali con quelli salvati.
     *
     * @param  array  $currentTags
     * @param  array  $savedTags
     * @return bool
     */
    protected function compareOsmTags(array $currentTags, array $savedTags): bool
    {
        // Normalizza i tag per il confronto (rimuovi chiavi che non sono rilevanti)
        $normalizeTags = function ($tags) {
            // Rimuovi osm_id se presente (non è un tag OSM)
            unset($tags['osm_id']);
            ksort($tags);

            return $tags;
        };

        $normalizedCurrent = $normalizeTags($currentTags);
        $normalizedSaved = $normalizeTags($savedTags);

        $changed = $normalizedCurrent !== $normalizedSaved;

        Log::debug('compareOsmTags: Comparison result', [
            'changed' => $changed,
            'current_keys' => array_keys($normalizedCurrent),
            'saved_keys' => array_keys($normalizedSaved),
        ]);

        return $changed;
    }

    /**
     * Confronta la geometria OSM attuale con quella salvata.
     *
     * @param  string  $currentGeometry PostGIS geometry string (WKT)
     * @param  mixed  $savedGeometry PostGIS geometry column
     * @return bool
     */
    protected function compareOsmGeometry(string $currentGeometry, $savedGeometry): bool
    {
        if (! $savedGeometry) {
            Log::debug('compareOsmGeometry: No saved geometry, considering changed', []);
            return true; // Se non c'è geometria salvata, considerala cambiata
        }

        try {
            Log::debug('compareOsmGeometry: Starting comparison', [
                'current_geometry_length' => strlen($currentGeometry),
                'has_saved_geometry' => true,
            ]);

            // getHikingRouteGeometry restituisce già una stringa PostGIS (WKT)
            // Converti entrambe le geometrie in formato testuale per il confronto
            $currentGeometryText = DB::selectOne(
                'SELECT ST_AsText(ST_Force3DZ(ST_GeomFromText(?), 0)) as geometry',
                [$currentGeometry]
            )->geometry ?? null;

            $savedGeometryText = DB::selectOne(
                'SELECT ST_AsText(ST_Force3DZ(?, 0)) as geometry',
                [$savedGeometry]
            )->geometry ?? null;

            if (! $currentGeometryText || ! $savedGeometryText) {
                Log::warning('compareOsmGeometry: Failed to convert geometries', [
                    'has_current_text' => $currentGeometryText !== null,
                    'has_saved_text' => $savedGeometryText !== null,
                ]);
                return true; // Se non riusciamo a confrontare, considerala cambiata
            }

            Log::debug('compareOsmGeometry: Geometries converted, comparing', [
                'current_text_length' => strlen($currentGeometryText),
                'saved_text_length' => strlen($savedGeometryText),
            ]);

            // Confronta usando ST_Equals
            $result = DB::selectOne(
                'SELECT NOT ST_Equals(ST_GeomFromText(?), ST_GeomFromText(?)) as changed',
                [$currentGeometryText, $savedGeometryText]
            );

            $changed = (bool) ($result->changed ?? true);

            Log::debug('compareOsmGeometry: Comparison result', [
                'changed' => $changed,
            ]);

            return $changed;
        } catch (\Exception $e) {
            Log::warning('compareOsmGeometry: Exception occurred, assuming changed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return true; // In caso di errore, assumiamo che sia cambiata
        }
    }

    /**
     * Determine if the current user can create new resources.
     */
    public static function authorizedToCreate($request)
    {
        return false;
    }
}
