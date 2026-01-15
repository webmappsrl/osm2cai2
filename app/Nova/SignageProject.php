<?php

namespace App\Nova;

use Kongulov\NovaTabTranslatable\NovaTabTranslatable;
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
     * Ottimizzazione: non carica hikingRoutes nella lista per migliorare le performance.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        // Non caricare hikingRoutes nella lista per migliorare le performance
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
            Textarea::make(__('Description'), 'properties.description')
                ->nullable()
                ->rows(3),
            SignageMap::make(__('Geometry'), 'geometry'),
            BelongsToMany::make(__('Hiking Routes'), 'hikingRoutes', HikingRoute::class)
                ->searchable()
                ->onlyOnDetail(),
        ];

        return $fields;
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
            new Actions\ExportSignageProjectSignage(),
        ];
    }
}
