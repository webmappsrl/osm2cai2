<?php

namespace App\Nova;

use App\Enums\UserRole;
use Kongulov\NovaTabTranslatable\NovaTabTranslatable;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Osm2cai\SignageMap\SignageMap;
use Wm\WmPackage\Nova\AbstractGeometryResource;
use Wm\WmPackage\Nova\Fields\PropertiesPanel;

class SignageProject extends AbstractGeometryResource
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\SignageProject::class;

    /**
     * Number of resources to show per page via relationships.
     * Ottimizzazione: limita i record mostrati nel campo BelongsToMany a 25 per pagina
     * invece di caricare tutte le 625+ hiking routes contemporaneamente.
     *
     * @var int
     */
    public static $perPageViaRelationship = 25;

    /**
     * Build an "index" query for the given resource.
     * Ottimizzazione: carica user e count delle hiking routes per la visualizzazione nella index.
     * Mostra tutti i progetti segnaletica (ogni utente può vedere tutti i progetti).
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        // Carica l'utente per evitare query N+1
        $query->with('user');

        // Aggiungi il count delle hiking routes tramite subquery per ottimizzare
        $query->withCount([
            'hikingRoutes' => function ($query) {
                $query->where('app_id', 1);
            }
        ]);

        return $query;
    }

    /**
     * Build a "detail" query for the given resource.
     * Ottimizzazione: NON caricare hikingRoutes qui per evitare di caricare 625+ modelli in memoria.
     * Nova caricherà le hiking routes lazy quando necessario (quando l'utente apre/scorre il campo BelongsToMany).
     * Il metodo getFeatureCollectionMap() caricherà comunque le hiking routes quando necessario per la mappa.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function detailQuery(NovaRequest $request, $query)
    {
        // NON caricare hikingRoutes qui - Nova li caricherà lazy quando necessario
        // Questo evita di caricare 625+ modelli in memoria all'apertura della pagina
        // La mappa continuerà a funzionare perché getFeatureCollectionMap() carica le hiking routes
        // quando chiamato via API in modo asincrono
        return $query;
    }

    /**
     * Get the URI key for the resource.
     *
     * @return string
     */
    public static function uriKey()
    {
        return 'signage-projects';
    }

    /**
     * Get the displayable label of the resource.
     *
     * @return string
     */
    public static function label()
    {
        return __('Progetti Segnaletica');
    }

    /**
     * Get the displayable singular label of the resource.
     *
     * @return string
     */
    public static function singularLabel()
    {
        return __('Progetto Segnaletica');
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request): array
    {
        $fields =  [
            ID::make()->sortable(),
            Text::make(__('Name'), 'name')->required(),
            BelongsTo::make(__('User'), 'user', User::class)
                ->searchable()
                ->sortable()
                ->onlyOnIndex(),
            Text::make(__('Hiking Routes Count'), 'hiking_routes_count')
                ->sortable()
                ->onlyOnIndex(),
            Textarea::make(__('Description'), 'properties->description')
                ->nullable()
                ->rows(3)
                ->showOnDetail()
                ->showOnCreating()
                ->showOnUpdating(),
            SignageMap::make(__('Geometry'), 'geometry'),
            BelongsToMany::make(__('Hiking Routes'), 'hikingRoutes', HikingRoute::class)
                ->searchable()
                ->onlyOnDetail(),
        ];

        return $fields;
    }

    /**
     * Determine if the current user can create new models.
     * Gli admin e gli utenti con settori assegnati possono creare progetti segnaletica.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public static function authorizedToCreate($request)
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        // Gli admin possono sempre creare progetti
        if ($user->hasRole(UserRole::Administrator)) {
            return true;
        }

        // Altrimenti verifica se l'utente ha settori assegnati (direttamente o tramite region/province/area)
        return $user->getSectors()->isNotEmpty();
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request): array
    {
        return [
            (new Actions\ExportSignageProjectSignage())->exceptOnIndex(),
        ];
    }

    /**
     * Determine if the current user can update the given resource.
     * Gli admin possono sempre modificare, altrimenti solo il creatore.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public function authorizedToUpdate($request)
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        // Gli admin possono sempre modificare
        if ($user->hasRole(UserRole::Administrator)) {
            return true;
        }

        // Altrimenti solo il creatore del progetto può modificarlo
        return $this->resource->user_id === $user->id;
    }

    /**
     * Determine if the current user can delete the given resource.
     * Gli admin possono sempre eliminare, altrimenti solo il creatore.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    public function authorizedToDelete($request)
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        // Altrimenti solo il creatore del progetto può eliminarlo
        return $this->resource->user_id === $user->id;
    }

    /**
     * Determine if the current user can attach hiking routes to this signage project.
     * Gli admin possono sempre aggiungere hiking routes, altrimenti solo il creatore.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    public function authorizedToAttachAny(NovaRequest $request, $model)
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        // Altrimenti solo il creatore del progetto può aggiungere hiking routes
        return $model->user_id === $user->id;
    }

    /**
     * Determine if the current user can detach hiking routes from this signage project.
     * Gli admin possono sempre rimuovere hiking routes, altrimenti solo il creatore.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  \Illuminate\Database\Eloquent\Model  $relatedModel
     * @return bool
     */
    public function authorizedToDetach(NovaRequest $request, $model, $relatedModel)
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        // Altrimenti solo il creatore del progetto può rimuovere hiking routes
        return $model->user_id === $user->id;
    }
}
