<?php

namespace App\Nova;

use Eminiarts\Tabs\Tabs;
use App\Nova\Cards\RefCard;
use Laravel\Nova\Fields\ID;
use App\Nova\Cards\LinksCard;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Boolean;
use Eminiarts\Tabs\Traits\HasTabs;
use App\Nova\Cards\Osm2caiStatusCard;
use Eminiarts\Tabs\Tab;
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

        // Aggiungi i campi specifici della risorsa
        $specificFields = array_merge($this->getIndexFields(), $this->getDetailFields());

        // Unisci i campi filtrati con quelli specifici
        $allFields = array_merge($filteredFields, $specificFields);

        // Converti i campi in un array associativo per facilitare l'ordinamento
        $allFieldsAssoc = [];
        foreach ($allFields as $field) {
            // Usa 'name' per ordinare se è un campo specifico o 'attribute' se è un campo filtrato
            $key = $field->name ?? $field->attribute;
            $allFieldsAssoc[$key] = $field;
        }

        // Ordina i campi in base alla lista predefinita
        uasort($allFieldsAssoc, function ($a, $b) use ($order) {
            // Usa 'name' se esiste, altrimenti 'attribute'
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
            $osmfeaturesData = json_decode($hr->osmfeatures_data, true);
            $linksCardData = $hr->getDataForNovaLinksCard();
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

        foreach ($jsonKeys as $key) {
            $osmfeaturesFields[] = Text::make(ucfirst(str_replace('_', ' ', $key)), $key)
                ->sortable()
                ->resolveUsing(function () use ($key) {
                    return $this->{$key};
                })
                ->fillUsing(function ($request, $model, $attribute, $requestAttribute) use ($key) {
                    $model->{$key} = $request->get($requestAttribute);
                })->hideFromDetail();
        }

        return array_merge($osmfeaturesFields, $specificFields);
    }

    private function getDetailFields()
    {

        $jsonKeys = ['osm_id'];
        $osmfeaturesFields = [];

        foreach ($jsonKeys as $key) {
            $osmfeaturesFields[] = Text::make(ucfirst(str_replace('_', ' ', $key)), $key)
                ->sortable()
                ->resolveUsing(function () use ($key) {
                    return $this->{$key};
                })
                ->fillUsing(function ($request, $model, $attribute, $requestAttribute) use ($key) {
                    $model->{$key} = $request->get($requestAttribute);
                })->hideFromDetail();
        }
        $fields = [
            Text::make('Percorribilitá', function () {
                return 'TBI';
            })->onlyOnDetail(),
            Text::make('Legenda', function () {
                return "<ul><li>Linea blu: percorso OSM2CAI/OSM</li><li>Linea rossa: percorso caricato dall'utente</li></ul>";
            })->asHtml()->onlyOnDetail(),
        ];

        return array_merge($osmfeaturesFields, $fields);
    }

    private function getTabs()
    {
        $tabs =  [Tabs::make('Metadata', [
            Tab::make('Main', [
                Text::make('Source', function () {
                    return 'TBI';
                }),
                Text::make('Data ricognizione', function () {
                    return 'TBI';
                }),
                Text::make('Codice Sezione CAI', function () {
                    return 'TBI';
                }),
                Text::make('REF precedente', function () {
                    return 'TBI';
                }),
                Text::make('REF rei', function () {
                    return 'TBI';
                }),
                Text::make('REF regionale', function () {
                    return 'TBI';
                }),

            ]),
            Tab::make('General', [
                Text::make('Localitá di partenza', function () {
                    return 'TBI';
                }),
                Text::make('Localitá di arrivo', function () {
                    return 'TBI';
                }),
                Text::make('Nome del percorso', function () {
                    return 'TBI';
                }),
                Text::make('Tipo di rete escursionistica', function () {
                    return 'TBI';
                }),
                Text::make('Codice OSM segnaletica', function () {
                    return 'TBI';
                }),
                Text::make('Segnaletica descr. (EN)', function () {
                    return 'TBI';
                }),
                Text::make('Segnaletica descr. (IT)', function () {
                    return 'TBI';
                }),
                Text::make('Percorso ad anello', function () {
                    return 'TBI';
                }),
                Text::make('Nome rete escursionistica', function () {
                    return 'TBI';
                }),

            ]),
            Tab::make('Tech', [
                Text::make('Lunghezza in Km', function () {
                    return 'TBI';
                }),
                Text::make('Diff CAI', function () {
                    return 'TBI';
                }),
                Text::make('Dislivello positivo in metri', function () {
                    return 'TBI';
                }),
                Text::make('Dislivello negativo in metri', function () {
                    return 'TBI';
                }),
                Text::make('Durata (P->A)', function () {
                    return 'TBI';
                }),
                Text::make('Durata (A->P)', function () {
                    return 'TBI';
                }),
                Text::make('Quota Massima', function () {
                    return 'TBI';
                }),
                Text::make('Quota Minima', function () {
                    return 'TBI';
                }),
                Text::make('Quota punto di partenza', function () {
                    return 'TBI';
                }),
                Text::make('Quota punto di arrivo', function () {
                    return 'TBI';
                }),
            ]),
            Tab::make('Other', [
                Text::make('Descrizione (EN)', function () {
                    return 'TBI';
                }),
                Text::make('Descrizione (IT)', function () {
                    return 'TBI';
                }),
                Text::make('Manutenzione (EN)', function () {
                    return 'TBI';
                }),
                Text::make('MANUTENZIONE (IT)', function () {
                    return 'TBI';
                }),
                Text::make('Note (EN)', function () {
                    return 'TBI';
                }),
                Text::make('Note (IT)', function () {
                    return 'TBI';
                }),
                Text::make('Note di progetto', function () {
                    return 'TBI';
                }),
                Text::make('Operatore', function () {
                    return 'TBI';
                }),
                Text::make('Stato del percorso', function () {
                    return 'TBI';
                }),
                Text::make('Indirizzo web', function () {
                    return 'TBI';
                }),
                Text::make('Immagine su wikimedia', function () {
                    return 'TBI';
                }),
            ]),
            Tab::make('Content', [
                Text::make('Automatic Name (computed for TDH)', function () {
                    return 'TBI';
                }),
                Text::make('Automatic Abstract (computed for TDH)', function () {
                    return 'TBI';
                }),
                Text::make('Feature Image', function () {
                    return 'TBI';
                }),
                Text::make('Description CAI IT', function () {
                    return 'TBI';
                }),
            ]),
            Tab::make('Issues', [
                Text::make('Issue Status', function () {
                    return 'TBI';
                }),
                Text::make('Issue Description', function () {
                    return 'TBI';
                }),
                Text::make('Issue Date', function () {
                    return 'TBI';
                }),
                Text::make('Issue Author', function () {
                    return 'TBI';
                }),
                Text::make('Cronologia Percorribilità', function () {
                    return 'TBI';
                }),
            ]),
            Tab::make('POI', [
                Text::make('POI in buffer (1km)', function () {
                    return 'TBI';
                })
            ]),
            Tab::make('Huts', [
                Text::make('Huts nelle vicinanze', function () {
                    return 'TBI';
                })
            ]),
            Tab::make('Natural Springs', [
                Text::make('Huts nelle vicinanze', function () {
                    return 'TBI';
                })
            ])
        ])];

        return $tabs;
    }
}