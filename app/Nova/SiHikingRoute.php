<?php

namespace App\Nova;

use App\Enums\UserRole;
use App\Helpers\Osm2caiHelper;
use App\Models\HikingRoute as HikingRouteModel;
use App\Models\SiHikingRoute as SiHikingRouteModel;
use App\Models\User;
use App\Nova\Cards\LinksCard;
use Kongulov\NovaTabTranslatable\NovaTabTranslatable;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Tabs\Tab;
use Marshmallow\Tiptap\Tiptap;
use Wm\WmPackage\Nova\Fields\FeatureCollectionMap\src\FeatureCollectionMap;

class SiHikingRoute extends HikingRoute
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<SiHikingRouteModel>
     */
    public static $model = SiHikingRouteModel::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * Get the displayable label of the resource.
     *
     * @return string
     */
    public static function label()
    {
        return __('Hiking Routes');
    }

    /**
     * Get the displayable singular label of the resource.
     *
     * @return string
     */
    public static function singularLabel()
    {
        return __('Route');
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        // Tutte le hiking routes dell'app 2
        $query->where('app_id', 2);

        // ...associate al layer 6 tramite la tabella pivot layerables
        $query->whereIn('id', function ($sub) {
            $sub->select('layerable_id')
                ->from('layerables')
                ->where('layer_id', 6)
                ->where('layerable_type', HikingRouteModel::class);
        });

        // Eager load sezioni (clubs) e regioni per il referente regionale
        $query->with(['clubs.region']);

        return $query;
    }

    /**
     * Get the cards available for the request.
     */
    public function cards(NovaRequest $request): array
    {
        // Ottieni le cards dal parent (HikingRoute)
        // Il parent cerca HikingRouteModel, ma noi siamo SicaiRouteModel
        // Quindi sovrascriviamo per usare il modello corretto
        if ($request->resourceId) {
            $sicaiRoute = SiHikingRouteModel::find($request->resourceId);

            if ($sicaiRoute) {
                $linksCardData = $sicaiRoute->getDataForNovaLinksCard();

                return [
                    (new \App\Nova\Cards\RefCard($sicaiRoute))->onlyOnDetail(),
                    (new LinksCard($linksCardData))->onlyOnDetail(),
                    (new \App\Nova\Cards\Osm2caiStatusCard($sicaiRoute))->onlyOnDetail(),
                ];
            }
        }

        return [];
    }

    public function actions(NovaRequest $request): array
    {
        return [];
    }

    public function fields(NovaRequest $request): array
    {
        if ($request->isResourceIndexRequest()) {
            return $this->sicaiIndexFields($request);
        }
        if ($request->isResourceDetailRequest()) {
            return $this->sicaiDetailFields($request);
        }

        return $this->sicaiEditFields($request);
    }

    /**
     * Campi mostrati nella lista (index).
     * Aggiungi qui i campi che vuoi vedere in tabella.
     */
    protected function sicaiIndexFields(NovaRequest $request): array
    {
        $fields = [];
        $fields[] = ID::make()->sortable();
        $fields[] = Text::make(__('Name'), 'name');
        $fields[] = Text::make(__('congruenza'), 'properties->congruenza');
        $fields[] = Text::make(__('Email referente regionale'), 'properties->email_ref_regionale');
        $fields[] = Text::make(__('osmfeatures source_ref'), function () {
            return $this->resource->osmfeatures_data['properties']['source_ref'] ?? null;
        })->asHtml();
        $fields[] = Text::make(__('Referente sezione'), function () {
            $clubs = $this->resource->clubs ?? collect();
            if ($clubs->isEmpty()) {
                return __('Non impostato');
            }
            $regionIds = $clubs->pluck('region_id')->filter()->unique()->values()->all();
            $regionalReferents = [];
            if (! empty($regionIds)) {
                $users = User::role(UserRole::RegionalReferent)->whereIn('region_id', $regionIds)->get();
                foreach ($users as $user) {
                    $regionalReferents[$user->region_id][] = $user;
                }
            }
            $parts = [];
            foreach ($clubs as $club) {
                $refs = collect($regionalReferents[$club->region_id] ?? [])->map(function ($user) {
                    $name = e($user->name ?? '');
                    $email = $user->email ? '<a href="mailto:' . e($user->email) . '">' . e($user->email) . '</a>' : '';

                    return trim($name . ($email ? ' — ' . $email : ''));
                })->filter()->implode(', ');
                $parts[] = $club->name . ': ' . ($refs ?: __('Non impostato'));
            }

            return implode(' | ', $parts);
        })->asHtml();
        $fields[] = Text::make(__('Osmfeatures ID'), function () {
            return Osm2caiHelper::getOpenstreetmapUrlAsHtml($this->osmfeatures_id);
        })->asHtml();


        return $fields;
    }

    /**
     * Campi mostrati nella vista dettaglio.
     * Aggiungi qui i campi che vuoi vedere nella pagina di dettaglio.
     */
    protected function sicaiDetailFields(NovaRequest $request): array
    {
        $fields = [];
        if ($this->parent_hiking_route_id != null) {
            $fields[] = $this->getParentRouteField();
        }
        $fields[] = Text::make('id', 'id');
        $fields[] = FeatureCollectionMap::make(__('Geometry'), 'geometry');
        $fields[] = NovaTabTranslatable::make([Text::make('name', 'name'), Tiptap::make(__('description'), 'properties->description')]);
        $fields[] = Tab::group(__('Details'), [
            Tab::make(__('SICAI'), $this->getSicaiTabFields()),
            Tab::make(__('DEM'), $this->getDemTabFields()),
            Tab::make(__('OSMFEATURES'), $this->getOsmfeaturesTabFields()),
        ]);

        return $fields;
    }

    /**
     * Campi mostrati nel form di creazione/modifica.
     * Aggiungi qui i campi modificabili.
     */
    protected function sicaiEditFields(NovaRequest $request): array
    {
        return [
            // es: $this->getReferenteNameField(), $this->getReferenteEmailField(), ...
        ];
    }

    /**
     * Field: link al parent hiking route (sola visualizzazione).
     */
    protected function getParentRouteField(): Text
    {
        return Text::make(__('Parent Hiking Route'), function () {
            $parentHikingRouteId = $this->resource->parent_hiking_route_id ?? null;
            if (! $parentHikingRouteId) {
                return __('Not set');
            }
            $url = '/resources/hiking-routes/' . $parentHikingRouteId;

            return "<a href='{$url}' target='_blank' style='color: #2697bc; text-decoration: none; font-weight: bold;'>{$parentHikingRouteId}</a>";
        })->asHtml();
    }
    public function getGeneralTabFields(): array
    {
        return [
            NovaTabTranslatable::make([
                Text::make(__('Name'), 'name'),
                Tiptap::make(__('description'), 'properties->description')
            ])
        ];
    }
    public function getOsmfeaturesTabFields(): array
    {
        $props = fn() => $this->resource->osmfeatures_data['properties'] ?? [];

        return [
            Text::make(__('Osmfeatures ID (interno)'), 'osmfeatures_id'),
            Text::make(__('OSM (relation/way)'), function () use ($props) {
                $p = $props();
                $osmType = $p['osm_type'] ?? null;
                $osmId = $p['osm_id'] ?? null;
                if ($osmType === null || $osmId === null) {
                    return '—';
                }
                $osmRef = $osmType . (string) $osmId;

                return Osm2caiHelper::getOpenstreetmapUrlAsHtml($osmRef);
            })->asHtml(),
            Text::make(__('osm_api'), function () use ($props) {
                $url = $props()['osm_api'] ?? null;
                if (! $url) {
                    return '—';
                }

                return '<a href="' . e($url) . '" target="_blank" rel="noopener">' . e($url) . '</a>';
            })->asHtml(),
            Text::make(__('ref'), fn() => $props()['ref'] ?? '—'),
            Text::make(__('ref_REI'), fn() => $props()['ref_REI'] ?? '—'),
            Text::make(__('source_ref'), fn() => $props()['source_ref'] ?? '—'),
            Text::make(__('from'), fn() => $props()['from'] ?? '—'),
            Text::make(__('to'), fn() => $props()['to'] ?? '—'),
            Text::make(__('name'), fn() => $props()['name'] ?? '—'),
            Text::make(__('network'), fn() => $props()['network'] ?? '—'),
            Text::make(__('cai_scale'), fn() => $props()['cai_scale'] ?? '—'),
            Text::make(__('symbol'), fn() => $props()['symbol'] ?? '—'),
            Text::make(__('symbol_it'), fn() => $props()['symbol_it'] ?? '—'),
            Text::make(__('osmc_symbol'), fn() => $props()['osmc_symbol'] ?? '—'),
            Text::make(__('source'), fn() => $props()['source'] ?? '—'),
            Text::make(__('operator'), fn() => $props()['operator'] ?? '—'),
            Text::make(__('website'), function () use ($props) {
                $url = $props()['website'] ?? null;
                if (! $url) {
                    return '—';
                }

                return '<a href="' . e($url) . '" target="_blank" rel="noopener">' . e($url) . '</a>';
            })->asHtml(),
            Text::make(__('score'), function () use ($props) {
                $s = $props()['score'] ?? null;
                if ($s === null) {
                    return '—';
                }

                return Osm2caiHelper::getScoreAsStars((int) $s) . " ({$s})";
            })->asHtml(),
            Text::make(__('ascent'), fn() => $props()['ascent'] ?? '—'),
            Text::make(__('descent'), fn() => $props()['descent'] ?? '—'),
            Text::make(__('distance'), fn() => $props()['distance'] ?? '—'),
            Text::make(__('duration_forward'), fn() => $props()['duration_forward'] ?? '—'),
            Text::make(__('duration_backward'), fn() => $props()['duration_backward'] ?? '—'),
            Text::make(__('roundtrip'), fn() => ($props()['roundtrip'] ?? null) === true ? __('Yes') : (($props()['roundtrip'] ?? null) === false ? __('No') : '—')),
            Text::make(__('state'), fn() => $props()['state'] ?? '—'),
            Text::make(__('osm2cai_status'), fn() => $props()['osm2cai_status'] ?? '—'),
            Text::make(__('updated_at'), fn() => $props()['updated_at'] ?? '—'),
            Text::make(__('updated_at_osm'), fn() => $props()['updated_at_osm'] ?? '—'),
            Text::make(__('survey_date'), fn() => $props()['survey_date'] ?? '—'),
            Text::make(__('description'), fn() => $props()['description'] ?? '—'),
            Text::make(__('description_it'), fn() => $props()['description_it'] ?? '—'),
            Text::make(__('note'), fn() => $props()['note'] ?? '—'),
            Text::make(__('note_it'), fn() => $props()['note_it'] ?? '—'),
            Text::make(__('maintenance'), fn() => $props()['maintenance'] ?? '—'),
            Text::make(__('maintenance_it'), fn() => $props()['maintenance_it'] ?? '—'),
            Text::make(__('note_project_page'), fn() => $props()['note_project_page'] ?? '—'),
            Text::make(__('old_ref'), fn() => $props()['old_ref'] ?? '—'),
            Text::make(__('rwn_name'), fn() => $props()['rwn_name'] ?? '—'),
            Text::make(__('wikidata'), fn() => $props()['wikidata'] ?? '—'),
            Text::make(__('wikipedia'), fn() => $props()['wikipedia'] ?? '—'),
            Text::make(__('wikimedia_commons'), fn() => $props()['wikimedia_commons'] ?? '—'),
            Text::make(__('dem_enrichment'), fn() => $props()['dem_enrichment'] !== null ? json_encode($props()['dem_enrichment'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '—'),
            Code::make(__('osm_tags'), function () use ($props) {
                $tags = $props()['osm_tags'] ?? null;
                if (! is_array($tags)) {
                    return '';
                }

                return json_encode($tags, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            })->json()->onlyOnDetail(),
            Text::make(__('admin_areas'), function () use ($props) {
                $adminAreas = $props()['admin_areas']['admin_area'] ?? null;
                if (! is_array($adminAreas)) {
                    return '—';
                }
                $labels = [
                    '2' => __('Stato'),
                    '4' => __('Regione'),
                    '6' => __('Provincia'),
                    '7' => __('Area (liv. 7)'),
                    '8' => __('Comune'),
                    '10' => __('Unità amministrative (liv. 10)'),
                ];
                $out = [];
                foreach ($adminAreas as $level => $items) {
                    if (! is_array($items)) {
                        continue;
                    }
                    $label = $labels[$level] ?? __('Livello') . ' ' . $level;
                    $names = array_map(fn($a) => ($a['name'] ?? '') . ' (' . ($a['osmfeatures_id'] ?? '') . ')', $items);
                    $out[] = '<strong>' . e($label) . '</strong>: ' . implode(', ', array_map('e', $names));
                }

                return implode('<br>', $out);
            })->asHtml()->onlyOnDetail(),
        ];
    }
    public function getDemTabFields(): array
    {
        return [
            Boolean::make(__('Round Trip'), 'properties->dem_data->round_trip'),
            Text::make(__('Ascent'), 'properties->dem_data->ascent'),
            Text::make(__('Descent'), 'properties->dem_data->descent'),
            Text::make(__('Distance'), 'properties->dem_data->distance'),
            Text::make(__('Maximum Elevation'), 'properties->dem_data->ele_max'),
            Text::make(__('Minimum Elevation'), 'properties->dem_data->ele_min'),
            Text::make(__('Starting Point Elevation'), 'properties->dem_data->ele_from'),
            Text::make(__('Ending Point Elevation'), 'properties->dem_data->ele_to'),
            Text::make(__('Duration Forward'), 'properties->dem_data->duration_forward'),
            Text::make(__('Duration Backward'), 'properties->dem_data->duration_backward'),
            Text::make(__('Duration Forward (bike)'), 'properties->dem_data->duration_forward_bike'),
            Text::make(__('Duration Backward (bike)'), 'properties->dem_data->duration_backward_bike'),
            Text::make(__('Duration Forward (hiking)'), 'properties->dem_data->duration_forward_hiking'),
            Text::make(__('Duration Backward (hiking)'), 'properties->dem_data->duration_backward_hiking'),
        ];
    }
    public function getSicaiTabFields(): array
    {
        return [
            Text::make(__('Tappa'), 'properties->sicai_properties->tappa'),
            Text::make(__('Sezione'), 'properties->sicai_properties->sezione'),
            Boolean::make(__('Parcheggio'), 'properties->sicai_properties->parcheggio'),
            Boolean::make(__('Stazione bus'), 'properties->sicai_properties->stazioni->bus'),
            Boolean::make(__('Stazione treno'), 'properties->sicai_properties->stazioni->treno'),
            Text::make(__('Verifica'), 'properties->sicai_properties->verifica'),
            Text::make(__('Descrizione'), 'properties->sicai_properties->descrizione'),
            Text::make(__('Segnaletica'), 'properties->sicai_properties->segnaletica'),
            Text::make(__('Segnalazioni'), 'properties->sicai_properties->segnalazioni'),
            Boolean::make(__('Punto accoglienza'), 'properties->sicai_properties->pt_accoglienza'),
            Text::make(__('Percorribilità'), 'properties->sicai_properties->percorribilità'),
            Text::make(__('Referente Nome'), 'properties->sicai_properties->referente->name'),
            Text::make(__('Referente Email'), 'properties->sicai_properties->referente->email'),
            Text::make(__('Email referente regionale'), 'properties->sicai_properties->email_ref_regionale'),
            Text::make(__('Referente regionale'), 'properties->sicai_properties->referente_regionale'),
            Text::make(__('Sezioni manutenzione'), 'properties->sicai_properties->sezioni_manutenzione'),
            Text::make(__('Sezione referente regionale'), 'properties->sicai_properties->sezione_ref_regionale'),
            Text::make(__('Note'), 'properties->sicai_properties->note'),
        ];
    }
    /**
     * Field: referente nome + email formattati (sola visualizzazione).
     */
    protected function getReferenteDisplayField(): Text
    {
        return Text::make(__('Referente'), function () {
            $referente = $this->resource->properties['referente'] ?? null;
            if (! $referente || (empty($referente['name']) && empty($referente['email']))) {
                return __('Non impostato');
            }
            $name = $referente['name'] ?? '';
            $email = $referente['email'] ?? '';
            $parts = [];
            if ($name !== '') {
                $parts[] = e($name);
            }
            if ($email !== '') {
                $parts[] = '<a href="mailto:' . e($email) . '">' . e($email) . '</a>';
            }

            return implode(' — ', $parts);
        })->asHtml();
    }

    /**
     * Field: nome referente (modificabile, scrive in properties->referente->name).
     */
    protected function getReferenteNameField(): Text
    {
        return Text::make(__('Referente Nome'), 'referente_name')
            ->resolveUsing(function () {
                $referente = $this->resource->properties['referente'] ?? null;

                return $referente['name'] ?? null;
            })
            ->fillUsing(function (NovaRequest $request, $model, $attribute, $requestAttribute) {
                $props = $model->properties ?? [];
                $referente = $props['referente'] ?? [];
                $referente['name'] = $request->$requestAttribute;
                $props['referente'] = $referente;
                $model->properties = $props;
            });
    }

    /**
     * Field: email referente (modificabile, scrive in properties->referente->email).
     */
    protected function getReferenteEmailField(): Text
    {
        return Text::make(__('Referente Email'), 'referente_email')
            ->resolveUsing(function () {
                $referente = $this->resource->properties['referente'] ?? null;

                return $referente['email'] ?? null;
            })
            ->fillUsing(function (NovaRequest $request, $model, $attribute, $requestAttribute) {
                $props = $model->properties ?? [];
                $referente = $props['referente'] ?? [];
                $referente['email'] = $request->$requestAttribute;
                $props['referente'] = $referente;
                $model->properties = $props;
            });
    }
}
