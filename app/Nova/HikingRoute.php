<?php

namespace App\Nova;

use App\Models\User;
use Eminiarts\Tabs\Tab;
use Eminiarts\Tabs\Tabs;
use App\Nova\Cards\RefCard;
use Laravel\Nova\Fields\ID;
use App\Nova\Cards\LinksCard;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Textarea;
use Eminiarts\Tabs\Traits\HasTabs;
use App\Nova\Cards\Osm2caiStatusCard;
use Laravel\Nova\Http\Requests\NovaRequest;

class HikingRoute extends OsmfeaturesResource
{
    use HasTabs;
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\HikingRoute>
     */
    public static $model = \App\Models\HikingRoute::class;

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
        'id',
        'osmfeatures_id'
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        // Ottieni i campi dal genitore
        $osmfeaturesFields = parent::fields($request);

        // Filtra i campi indesiderati
        $filteredFields = array_filter($osmfeaturesFields, function ($field) {
            return !in_array($field->attribute, ['id', 'name', 'osmfeatures_data', 'created_at', 'updated_at', 'osmfeatures_updated_at']);
        });

        // Definisci l'ordine desiderato dei campi
        $order = [
            'Osmfeatures ID' => 'Osmfeatures ID',
            'percorribilita' => 'Percorribilitá',
            'legenda' => 'Legenda',
            'geometry' => 'Geometry',
            'correttezza_geometria' => 'Correttezza Geometria',
            'coerenza_ref_rei' => 'Coerenza ref REI',
            'geometry_sync' => 'Geometry Sync'
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

        // Converti di nuovo in array indicizzato
        $orderedFields = array_values($allFieldsAssoc);

        return array_merge($orderedFields, [
            Boolean::make('Correttezza Geometria', function () {
                return 'TBI';
            })->onlyOnDetail(),
            Boolean::make('Coerenza ref REI', function () {
                return 'TBI';
            })->onlyOnDetail(),
            Boolean::make('Geometry Sync', function () {
                return 'TBI';
            })->onlyOnDetail(),
        ], $this->getTabs());
    }




    /**
     * Get the cards available for the request.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        // Verifica se l'ID della risorsa è presente nella richiesta
        if ($request->resourceId) {
            // Accedi al modello corrente tramite l'ID della risorsa
            $hr = \App\Models\HikingRoute::find($request->resourceId);
            $osmfeaturesData = $hr->osmfeatures_data;
            $linksCardData = $hr->getDataForNovaLinksCard();
            if (is_string($osmfeaturesData)) $osmfeaturesData = json_decode($osmfeaturesData, true);
            $refCardData = $osmfeaturesData['properties']['osm_tags'];
            $osm2caiStatusCardData = $osmfeaturesData['properties']['osm2cai_status'];

            return [
                (new RefCard($refCardData))->onlyOnDetail(),
                (new LinksCard($linksCardData))->onlyOnDetail(),
                (new Osm2caiStatusCard($osm2caiStatusCardData))->onlyOnDetail(),
            ];
        }


        // Restituisci un array vuoto se non sei nel dettaglio o non ci sono dati
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        $parentFilters = parent::filters($request);
        //remove score filter
        unset($parentFilters[0]);

        return $parentFilters;
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }

    private function getIndexFields()
    {
        $jsonKeys = ['osm2cai_status'];

        $specificFields = [
            Text::make('Regioni', function () {
                return 'TBI';
            })->hideFromDetail(),
            Text::make('Province', function () {
                return 'TBI';
            })->hideFromDetail(),
            Text::make('Aree', function () {
                return 'TBI';
            })->hideFromDetail(),
            Text::make('Settori', function () {
                return 'TBI';
            })->hideFromDetail(),
            Text::make('Ref', function () {
                return 'TBI';
            })->hideFromDetail(),
            Text::make('Cod_rei_osm', function () {
                return 'TBI';
            })->hideFromDetail(),
            Text::make('Cod_rei_comp', function () {
                return 'TBI';
            })->hideFromDetail(),
            Text::make('Percorribilitá', function () {
                return 'TBI';
            })->hideFromDetail(),
            Text::make('Ultima Ricognizione', function () {
                return 'TBI';
            })->hideFromDetail(),
        ];

        // Generate fields using dot notation for JSON data
        $osmfeaturesFields = [];
        foreach ($jsonKeys as $key) {
            $osmfeaturesFields[] = Text::make(
                ucfirst(str_replace('_', ' ', $key)), // Label
                "osmfeatures_data->properties->$key" // Use the `->` notation for JSON access
            )
                ->sortable()
                ->resolveUsing(function ($value) {
                    return $value;
                })
                ->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                    $model->{$attribute} = $request->get($requestAttribute);
                })
                ->hideFromDetail();
        }

        // Merge and return all fields
        return array_merge($osmfeaturesFields, $specificFields);
    }


    private function getDetailFields()
    {

        $jsonKeys = ['osm_id'];
        $osmfeaturesFields = [];

        foreach ($jsonKeys as $key) {
            $osmfeaturesFields[] = Text::make(
                ucfirst(str_replace('_', ' ', $key)), // Label
                "osmfeatures_data->properties->$key" // Use the `->` notation for JSON access
            )
                ->sortable()
                ->resolveUsing(function ($value) {
                    return $value;
                })
                ->fillUsing(function ($request, $model, $attribute, $requestAttribute) {
                    $model->{$attribute} = $request->get($requestAttribute);
                });
        }
        $fields = [
            Text::make('Legenda', function () {
                return <<<HTML
    <ul>
        <li>Linea blu: percorso OSM2CAI/OSM</li>
        <li>Linea rossa: percorso caricato dall'utente</li>
    </ul>
    HTML;
            })->asHtml()->onlyOnDetail(),

        ];

        return array_merge($osmfeaturesFields, $fields);
    }

    private function getTabs()
    {
        return [
            Tabs::make('Metadata', $this->getMetadataTabs()),
        ];
    }

    private function getMetadataTabs()
    {
        return [
            Tab::make('Main', $this->getMainTabFields()),
            Tab::make('General', $this->getGeneralTabFields()),
            Tab::make('Tech', $this->getTechTabFields()),
            Tab::make('Other', $this->getOtherTabFields()),
            Tab::make('Content', $this->getContentTabFields()),
            Tab::make('Issues', $this->getIssuesTabFields()),
            Tab::make('POI', $this->getPOITabFields()),
            Tab::make('Huts', $this->getHutsTabFields()),
            Tab::make('Natural Springs', $this->getNaturalSpringsTabFields()),
        ];
    }

    private function getMainTabFields()
    {
        return [
            Text::make('Source', "osmfeatures_data->properties->source")->hideFromIndex(),
            Text::make('Data ricognizione', fn() => 'TBI')->hideFromIndex(),
            Text::make('Codice Sezione CAI', fn() => 'TBI')->hideFromIndex(),
            Text::make('REF precedente', fn() => 'TBI')->hideFromIndex(),
            Text::make('REF rei', 'osmfeatures_data->properties->ref_REI')->hideFromIndex(),
            Text::make('REF regionale', fn() => 'TBI')->hideFromIndex(),
        ];
    }

    private function getGeneralTabFields()
    {
        return [
            Text::make('Localitá di partenza', 'osmfeatures_data->properties->from')->hideFromIndex(),
            Text::make('Localitá di arrivo', 'osmfeatures_data->properties->to')->hideFromIndex(),
            Text::make('Nome del percorso', 'osmfeatures_data->properties->name')->hideFromIndex(),
            Text::make('Tipo di rete escursionistica', 'osmfeatures_data->properties->osm_tags->type')->hideFromIndex(),
            Text::make('Codice OSM segnaletica', 'osmfeatures_data->properties->osm_tags->osmc:symbol')->hideFromIndex(),
            Text::make('Segnaletica descr. (EN)', 'osmfeatures_data->properties->osm_tags->symbol')->hideFromIndex(),
            Text::make('Segnaletica descr. (IT)', 'osmfeatures_data->properties->osm_tags->symbol:it')->hideFromIndex(),
            Text::make('Percorso ad Anello', 'osmfeatures_data->properties->roundtrip')->hideFromIndex(),
            Text::make('Nome rete escursionistica', 'osmfeatures_data->properties->rwn_name')->hideFromIndex(),
        ];
    }

    private function getTechTabFields()
    {
        return [
            Text::make('Lunghezza in Km', 'osmfeatures_data->properties->dem_enrichment->distance')->hideFromIndex(),
            Text::make('Diff CAI', 'osmfeatures_data->properties->cai_scale')->hideFromIndex(),
            Text::make('Dislivello positivo in metri', 'osmfeatures_data->properties->dem_enrichment->ascent')->hideFromIndex(),
            Text::make('Dislivello negativo in metri', 'osmfeatures_data->properties->dem_enrichment->descent')->hideFromIndex(),
            Text::make('Durata (P->A) in minuti', 'osmfeatures_data->properties->dem_enrichment->duration_forward_hiking')->hideFromIndex(),
            Text::make('Durata (A->P) in minuti', 'osmfeatures_data->properties->dem_enrichment->duration_backward_hiking')->hideFromIndex(),
            Text::make('Quota Massima in metri', 'osmfeatures_data->properties->dem_enrichment->ele_max')->hideFromIndex(),
            Text::make('Quota Minima in metri', 'osmfeatures_data->properties->dem_enrichment->ele_min')->hideFromIndex(),
            Text::make('Quota punto di partenza in metri', 'osmfeatures_data->properties->dem_enrichment->ele_from')->hideFromIndex(),
            Text::make('Quota punto di arrivo in metri', 'osmfeatures_data->properties->dem_enrichment->ele_to')->hideFromIndex(),
        ];
    }

    private function getOtherTabFields()
    {
        return [
            Text::make('Descrizione (EN)', 'osmfeatures_data->properties->description')->hideFromIndex(),
            Text::make('Descrizione (IT)', 'osmfeatures_data->properties->description_it')->hideFromIndex(),
            Text::make('Manutenzione (EN)', 'osmfeatures_data->properties->maintenance')->hideFromIndex(),
            Text::make('MANUTENZIONE (IT)', 'osmfeatures_data->properties->maintenance_it')->hideFromIndex(),
            Text::make('Note (EN)', 'osmfeatures_data->properties->note')->hideFromIndex(),
            Text::make('Note (IT)', 'osmfeatures_data->properties->note_it')->hideFromIndex(),
            Text::make('Note di progetto', 'osmfeatures_data->properties->note_project_page')->hideFromIndex(),
            Text::make('Operatore', 'osmfeatures_data->properties->osm_tags->operator')->hideFromIndex(),
            Text::make('Stato del percorso', fn() => 'TBI')->hideFromIndex(),
            Text::make('Indirizzo web', 'osmfeatures_data->properties->website')->hideFromIndex()->displayUsing(fn($value) => '<a style="color:blue;" href="' . $value . '" target="_blank">' . $value . '</a>')->asHtml(),
            Text::make('Immagine su wikimedia', 'osmfeatures_data->properties->wikimedia_commons')->hideFromIndex()->displayUsing(fn($value) => '<a style="color:blue;" href="' . $value . '" target="_blank">' . $value . '</a>')->asHtml(),
        ];
    }

    private function getContentTabFields()
    {
        return [
            Text::make('Automatic Name (computed for TDH)', fn() => 'TBI')->hideFromIndex(),
            Text::make('Automatic Abstract (computed for TDH)', fn() => 'TBI')->hideFromIndex(),
            Text::make('Feature Image', fn() => 'TBI')->hideFromIndex(),
            Text::make('Description CAI IT', fn() => 'TBI')->hideFromIndex(),
        ];
    }

    private function getIssuesTabFields()
    {
        return [
            Text::make('Issue Status', 'issues_status')->hideFromIndex(),
            Textarea::make('Issue Description', 'issues_description')->hideFromIndex(),
            Date::make('Issue Date', 'issues_last_update')->hideFromIndex(),
            Text::make('Issue Author', function () {
                $user = User::find($this->model()->issues_user_id);
                return $user
                    ? '<a style="color:blue;" href="' . url('/resources/users/' . $user->id) . '" target="_blank">' . $user->name . '</a>'
                    : 'No user';
            })->hideFromIndex()->asHtml(),
            Text::make('Cronologia Percorribilità', 'issues_chronology')->hideFromIndex(),
        ];
    }

    private function getPOITabFields()
    {
        return [
            Text::make('POI in buffer (1km)', fn() => 'TBI')->hideFromIndex(),
        ];
    }

    private function getHutsTabFields()
    {
        return [
            Text::make('Huts nelle vicinanze', fn() => 'TBI')->hideFromIndex(),
        ];
    }

    private function getNaturalSpringsTabFields()
    {
        return [
            Text::make('Huts nelle vicinanze', fn() => 'TBI')->hideFromIndex(),
        ];
    }
}
