<?php

namespace App\Nova;

use App\Enums\UserRole;
use App\Helpers\Osm2caiHelper;
use App\Models\SicaiRoute as SicaiRouteModel;
use App\Models\User;
use App\Nova\Cards\LinksCard;
use App\Nova\Sector;
use Kongulov\NovaTabTranslatable\NovaTabTranslatable;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Monolog\Handler\BrowserConsoleHandler;
use Osm2cai\SignageMap\SignageMap;

class SicaiRoute extends HikingRoute
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<SicaiRouteModel>
     */
    public static $model = SicaiRouteModel::class;

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
        return __('Routes');
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
        // Filtra solo le routes con app_id = 2
        $query->where('app_id', 2);
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
            $sicaiRoute = SicaiRouteModel::find($request->resourceId);

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
        $fields[] = Text::make(__('Osmfeatures ID'), function () {
            return Osm2caiHelper::getOpenstreetmapUrlAsHtml($this->osmfeatures_id);
        })->asHtml();
        $fields[] = Text::make('name', 'name');
        $fields[] = $this->getReferenteDisplayField();
        $fields[] = NovaTabTranslatable::make([Text::make(__('description'), 'properties->description')]);
        $fields[] = SignageMap::make(__('Geometry'), 'geometry');
        $fields[] = Boolean::make(__('reachable train'), 'properties->reachable->train');
        $fields[] = Boolean::make(__('reachable bus'), 'properties->reachable->bus');

        $fields[] = Boolean::make(__('parking'), 'properties->parking');
        $fields[] = Boolean::make(__('reception_points'), 'properties->reception_points');

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
