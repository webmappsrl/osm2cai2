<?php

namespace App\Nova;

use App\Models\User;
use App\Models\EcPoi;
use Eminiarts\Tabs\Tab;
use Eminiarts\Tabs\Tabs;
use App\Nova\Cards\RefCard;
use Illuminate\Support\Arr;
use Laravel\Nova\Fields\ID;
use App\Nova\Cards\LinksCard;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\Boolean;
use App\Nova\Filters\ScoreFilter;
use Laravel\Nova\Fields\Textarea;
use Eminiarts\Tabs\Traits\HasTabs;
use App\Nova\Cards\Osm2caiStatusCard;
use Laravel\Nova\Http\Requests\NovaRequest;
use Ebess\AdvancedNovaMediaLibrary\Fields\Images;

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
        'osmfeatures_id',
    ];

    public function title()
    {
        $supplementaryString = ' - ';

        if ($this->name) {
            $supplementaryString .= $this->name;
        }

        if ($this->ref)
            $supplementaryString .= 'ref: ' . $this->ref;

        if ($this->sectors->count()) {
            $supplementaryString .= " (" . $this->sectors->pluck('name')->implode(', ') . ")";
        }

        return $this->id . $supplementaryString;
    }

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
            return ! in_array($field->attribute, ['id', 'name', 'osmfeatures_data', 'created_at', 'updated_at', 'osmfeatures_updated_at']);
        });

        // Definisci l'ordine desiderato dei campi
        $order = [
            'Osmfeatures ID' => 'Osmfeatures ID',
            'percorribilita' => 'Percorribilitá',
            'legenda' => 'Legenda',
            'geometry' => 'Geometry',
            'correttezza_geometria' => 'Correttezza Geometria',
            'coerenza_ref_rei' => 'Coerenza ref REI',
            'geometry_sync' => 'Geometry Sync',
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
                return $this->hasCorrectGeometry();
            })->onlyOnDetail(),
            Boolean::make('Coerenza ref REI', function () {
                return $this->osmfeatures_data['properties']['ref_REI'] == $this->ref_rei;
            })->onlyOnDetail(),
            Boolean::make('Geometry Sync', function () {
                return $this->geometry == $this->osmfeatures_data['geometry'];
            })->onlyOnDetail(),
        ], $this->getTabs());
    }

    /**
     * Get the cards available for the request.
     *
     * @param  NovaRequest  $request
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
            if (is_string($osmfeaturesData)) {
                $osmfeaturesData = json_decode($osmfeaturesData, true);
            }

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
     * @param  NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        $parentFilters = parent::filters($request);
        //remove App\Nova\Filters\ScoreFilter from $parentFilters array
        foreach ($parentFilters as $key => $filter) {
            if ($filter instanceof ScoreFilter) {
                unset($parentFilters[$key]);
            }
        }
        return $parentFilters;
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }

    private function getIndexFields()
    {
        $specificFields = [
            Text::make('Osm2cai Status', 'osm2cai_status')
                ->hideFromDetail(),
            Text::make('Regioni', function () {
                $val = "ND";
                if (Arr::accessible($this->regions)) {
                    if (count($this->regions) > 0) {
                        $val = implode(', ', $this->regions->pluck('name')->toArray());
                    }
                    if (count($this->regions) >= 2) {
                        $val = implode(', ', $this->regions->pluck('name')->take(1)->toArray()) . ' [...]';
                    }
                }
                return $val;
            })->onlyOnIndex(),
            Text::make('Province', function () {
                $val = "ND";
                if (Arr::accessible($this->provinces)) {
                    if (count($this->provinces) > 0) {
                        $val = implode(', ', $this->provinces->pluck('name')->toArray());
                    }
                    if (count($this->provinces) >= 2) {
                        $val = implode(', ', $this->provinces->pluck('name')->take(1)->toArray()) . ' [...]';
                    }
                }
                return $val;
            })->onlyOnIndex(),
            Text::make('Aree', function () {
                $val = "ND";
                if (Arr::accessible($this->areas)) {
                    if (count($this->areas) > 0) {
                        $val = implode(', ', $this->areas->pluck('name')->toArray());
                    }
                    if (count($this->areas) >= 2) {
                        $val = implode(', ', $this->areas->pluck('name')->take(1)->toArray()) . ' [...]';
                    }
                }
                return $val;
            })->onlyOnIndex(),
            Text::make('Settori', function () {
                $val = "ND";
                if (Arr::accessible($this->sectors)) {
                    if (count($this->sectors) > 0) {
                        $val = implode(', ', $this->sectors->pluck('name')->toArray());
                    }
                    if (count($this->sectors) >= 2) {
                        $val = implode(', ', $this->areas->pluck('name')->take(1)->toArray()) . ' [...]';
                    }
                }
                return $val;
            })->onlyOnIndex(),
            Text::make('REF', 'osmfeatures_data->properties->ref')->onlyOnIndex()->sortable(),
            Text::make('Cod_REI', 'ref_rei')->hideFromDetail(),
            Text::make('Percorribilitá', 'issues_status')->hideFromDetail(),
            Text::make('Ultima Ricognizione', 'osmfeatures_data->properties->survey_date')->hideFromDetail(),
        ];

        return $specificFields;
    }

    private function getDetailFields()
    {
        $fields = [
            Text::make('OSM ID', 'osmfeatures_data->properties->osm_id')->onlyOnDetail(),
            Text::make('Legenda', function () {
                return <<<'HTML'
    <ul>
        <li>Linea blu: percorso OSM2CAI/OSM</li>
        <li>Linea rossa: percorso caricato dall'utente</li>
    </ul>
    HTML;
            })->asHtml()->onlyOnDetail(),

        ];

        return $fields;
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

    private function createField($label, $infomont, $osmPath, $modelAttribute = null, $isLink = false, $withCalculated = false)
    {
        return Text::make($label, function () use ($infomont, $osmPath, $modelAttribute, $isLink, $withCalculated) {
            $osmValue = $this->getOsmValue($osmPath);

            if ($isLink && $osmValue) {
                $osmValue = "<a style='color:blue;' href='{$osmValue}' target='_blank'>{$osmValue}</a>";
            }

            $infomontValue = $modelAttribute ? $this->$modelAttribute : $this->$infomont;

            $html = "<p>INFOMONT: {$infomontValue}</p><p>OSM: {$osmValue}</p>";

            if ($withCalculated) {
                $calculated = $this->getOsmValue(str_replace('properties', 'properties.dem_enrichment', $osmPath));
                $html .= "<p>VALORE CALCOLATO: {$calculated}</p>";
            }

            return $html;
        })->onlyOnDetail()->asHtml();
    }

    private function getOsmValue($path)
    {
        $keys = explode('.', $path);
        $value = $this->osmfeatures_data;

        foreach ($keys as $key) {
            if (!isset($value[$key])) {
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
            $this->createField('Data ricognizione', 'survey_date', 'properties.survey_date'),
            $this->createField('Codice Sezione CAI', 'source_ref', 'properties.osm_tags.source:ref'),
            $this->createField('REF precedente', 'old_ref', 'properties.old_ref'),
            $this->createField('REF rei', 'ref_rei', 'properties.ref_REI'),
            $this->createField('REF regionale', 'reg_ref', 'properties.osm_tags.reg_ref'),
        ];
    }

    private function getGeneralTabFields()
    {
        return [
            $this->createField('Localitá di partenza', 'from', 'properties.from'),
            $this->createField('Localitá di arrivo', 'to', 'properties.to'),
            $this->createField('Nome del percorso', 'name', 'properties.name'),
            $this->createField('Tipo di rete escursionistica', 'type', 'properties.osm_tags.type'),
            $this->createField('Codice OSM segnaletica', 'osmc_symbol', 'properties.osm_tags.osmc:symbol'),
            $this->createField('Segnaletica descr. (EN)', 'symbol', 'properties.osm_tags.symbol'),
            $this->createField('Segnaletica descr. (IT)', 'symbol_it', 'properties.osm_tags.symbol:it'),
            $this->createField('Percorso ad Anello', 'roundtrip', 'properties.roundtrip'),
            $this->createField('Nome rete escursionistica', 'rwn_name', 'properties.rwn_name'),
        ];
    }

    private function getTechTabFields()
    {
        $techFields = [
            'Lunghezza in Km' => ['distance', 'properties.distance'],
            'Diff CAI' => ['cai_scale', 'properties.cai_scale'],
            'Dislivello positivo in metri' => ['ascent', 'properties.ascent'],
            'Dislivello negativo in metri' => ['descent', 'properties.descent'],
            'Durata (P->A) in minuti' => ['duration_forward', 'properties.duration_forward'],
            'Durata (A->P) in minuti' => ['duration_backward', 'properties.duration_backward'],
            'Quota Massima in metri' => ['ele_max', 'properties.ele_max'],
            'Quota Minima in metri' => ['ele_min', 'properties.ele_min'],
            'Quota punto di partenza in metri' => ['ele_from', 'properties.ele_from'],
            'Quota punto di arrivo in metri' => ['ele_to', 'properties.ele_to'],
        ];

        return array_map(function ($label, $config) {
            //if diff cai
            if ($label == 'Diff CAI') {
                return $this->createField($label, $config[0], $config[1], null, false, false);
            }
            return $this->createField($label, $config[0], $config[1], null, false, true);
        }, array_keys($techFields), $techFields);
    }

    private function getOtherTabFields()
    {
        $fields = [
            'Descrizione (EN)' => ['description', 'properties.description'],
            'Descrizione (IT)' => ['description_it', 'properties.description_it'],
            'Manutenzione (EN)' => ['maintenance', 'properties.maintenance'],
            'Manutenzione (IT)' => ['maintenance_it', 'properties.maintenance_it'],
            'Note (EN)' => ['note', 'properties.note'],
            'Note (IT)' => ['note_it', 'properties.note_it'],
            'Note di progetto' => ['note_project_page', 'properties.note_project_page'],
            'Operatore' => ['operator', 'properties.osm_tags.operator'],
            'Stato del percorso' => ['state', 'properties.state'],
        ];

        $standardFields = array_map(function ($label, $config) {
            return $this->createField($label, $config[0], $config[1]);
        }, array_keys($fields), $fields);

        return array_merge($standardFields, [
            $this->createField('Indirizzo web', 'website', 'properties.website', null, true),
            $this->createField('Immagine su wikimedia', 'wikimedia_commons', 'properties.wikimedia_commons', null, true),
        ]);
    }

    private function getContentTabFields()
    {
        return [
            Text::make('Automatic Name (computed for TDH)', fn() => $this->getNameForTDH()['it'])->onlyOnDetail(),
            Text::make('Automatic Abstract (computed for TDH)', fn() => $this->tdh['abstract'] ?? '')->onlyOnDetail(),
            Images::make('Feature Image', 'feature_image')->onlyOnDetail(),
            Text::make('Description CAI IT', 'description_cai_it')->hideFromIndex(),
        ];
    }

    private function getIssuesTabFields()
    {
        return [
            Text::make('Issue Status', 'issues_status')->onlyOnDetail(),
            Textarea::make('Issue Description', 'issues_description')->onlyOnDetail(),
            Date::make('Issue Date', 'issues_last_update')->onlyOnDetail(),
            Text::make('Issue Author', function () {
                $user = User::find($this->model()->issues_user_id);

                return $user
                    ? '<a style="color:blue;" href="' . url('/resources/users/' . $user->id) . '" target="_blank">' . $user->name . '</a>'
                    : 'No user';
            })->hideFromIndex()->asHtml(),
            Text::make('Cronologia Percorribilità', 'issues_chronology')->onlyOnDetail(),
        ];
    }

    private function getPOITabFields()
    {
        $pois = $this->model()->getElementsInBuffer(new EcPoi(), 10000);
        $fields[] = Text::make('', function () use ($pois) {
            if (count($pois) < 1) {
                return '<h2 style="color:#666; font-size:1.5em; margin:20px 0;">Nessun POI trovato nel raggio di 1km</h2>';
            }
            return '<h2 style="color:#2697bc; font-size:1.5em; margin:20px 0;">Punti di interesse nel raggio di 1km</h2>';
        })->asHtml()->onlyOnDetail();

        if (count($pois) > 0) {
            $tableRows = [];
            foreach ($pois as $poi) {
                $tags = $poi->osmfeatures_data['properties']['osm_tags'];
                $tagList = '';
                if ($tags) {
                    $tagList = '<ul style="list-style:none; padding:0; margin:0;">';
                    foreach ($tags as $key => $value) {
                        $tagList .= "<li style='padding:3px 0;'><span style='color:#666; font-weight:bold;'>{$key}:</span> {$value}</li>";
                    }
                    $tagList .= '</ul>';
                }

                $tableRows[] = "<tr style='border-bottom:1px solid #eee; transition: background 0.2s;' onmouseover=\"this.style.background='#f5f5f5'\" onmouseout=\"this.style.background='white'\">
            <td style='padding:12px; border-right:1px solid #eee;'><a style='text-decoration: none; color: #2697bc; font-weight: bold; transition: color 0.2s;' href='/resources/ec-pois/{$poi->id}' onmouseover=\"this.style.color='#1a7594'\" onmouseout=\"this.style.color='#2697bc'\">{$poi->name}</a></td>
            <td style='padding:12px; border-right:1px solid #eee;'><code style='background:#f8f8f8; padding:2px 6px; border-radius:3px;'>{$poi->osmfeatures_data['properties']['osm_id']}</code></td>
            <td style='padding:12px; border-right:1px solid #eee;'>{$tagList}</td>
            <td style='padding:12px; text-align:center;'><span style='background:#e3f2fd; color:#1976d2; padding:4px 8px; border-radius:4px; font-size:0.9em;'>{$poi->osmfeatures_data['properties']['osm_type']}</span></td>
        </tr>";
            }

            $fields[] = Text::make('Risultati', function () use ($tableRows) {
                return "<div style='background:white; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.1); overflow:hidden; margin:10px 0;'>
                <table style='width:100%; border-collapse:collapse; background:white;'>
                    <thead>
                        <tr style='background:#f5f7fa;'>
                            <th style='padding:15px; text-align:left; color:#2697bc; font-weight:600; border-bottom:2px solid #eee;'>Name</th>
                            <th style='padding:15px; text-align:left; color:#2697bc; font-weight:600; border-bottom:2px solid #eee;'>OSM ID</th>
                            <th style='padding:15px; text-align:left; color:#2697bc; font-weight:600; border-bottom:2px solid #eee;'>Tag OSM</th>
                            <th style='padding:15px; text-align:center; color:#2697bc; font-weight:600; border-bottom:2px solid #eee;'>Type OSM</th>
                        </tr>
                    </thead>
                    <tbody>" . implode('', $tableRows) . "</tbody>
                </table>
                </div>";
            })->asHtml()->onlyOnDetail();
        }
        return $fields;
    }

    private function getHutsTabFields()
    {
        $hr = HikingRoute::find($this->id);
        $huts = $hr->nearbyCaiHuts;

        if (empty($huts)) {
            return [
                Text::make('', fn() => '<h2 style="color:#666; font-size:1.5em; margin:20px 0;">Nessun rifugio nelle vicinanze</h2>')->asHtml()->onlyOnDetail(),
            ];
        }
        $fields = [
            Text::make('', function () {
                return '<h2 style="color:#2697bc; font-size:1.5em; margin:20px 0;">Rifugi nelle vicinanze</h2>';
            })->asHtml()->onlyOnDetail()
        ];

        $tableRows = [];

        foreach ($huts as $hut) {
            $tableRows[] = "<tr style='border-bottom:1px solid #eee; transition: background 0.2s;' onmouseover=\"this.style.background='#f5f5f5'\" onmouseout=\"this.style.background='white'\">
                <td style='padding:12px; border-right:1px solid #eee;'><code style='background:#f8f8f8; padding:2px 6px; border-radius:3px;'>{$hut->id}</code></td>
                <td style='padding:12px; border-right:1px solid #eee;'><a style='text-decoration: none; color: #2697bc; font-weight: bold; transition: color 0.2s;' href='/resources/cai-huts/{$hut->id}' onmouseover=\"this.style.color='#1a7594'\" onmouseout=\"this.style.color='#2697bc'\">{$hut->name}</a></td>
            </tr>";
        }

        $fields[] = Text::make('Risultati', function () use ($tableRows) {
            return "<div style='background:white; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.1); overflow:hidden; margin:10px 0;'>
                <table style='width:100%; border-collapse:collapse; background:white;'>
                    <thead>
                        <tr style='background:#f5f7fa;'>
                            <th style='padding:15px; text-align:left; color:#2697bc; font-weight:600; border-bottom:2px solid #eee;'>ID</th>
                            <th style='padding:15px; text-align:left; color:#2697bc; font-weight:600; border-bottom:2px solid #eee;'>Nome</th>
                        </tr>
                    </thead>
                    <tbody>" . implode('', $tableRows) . "</tbody>
                </table>
            </div>";
        })->asHtml()->onlyOnDetail();

        return $fields;
    }

    private function getNaturalSpringsTabFields()
    {
        $hr = HikingRoute::find($this->id);
        $naturalSprings = $hr->nearbyNaturalSprings;

        if (empty($naturalSprings)) {
            return [
                Text::make('', fn() => '<h2 style="color:#666; font-size:1.5em; margin:20px 0;">Nessuna sorgente naturale nelle vicinanze</h2>')->asHtml()->onlyOnDetail(),
            ];
        }

        $fields = [
            Text::make('', function () {
                return '<h2 style="color:#2697bc; font-size:1.5em; margin:20px 0;">Sorgenti naturali nelle vicinanze</h2>';
            })->asHtml()->onlyOnDetail()
        ];

        $tableRows = [];

        foreach ($naturalSprings as $naturalSpring) {
            $tableRows[] = "<tr style='border-bottom:1px solid #eee; transition: background 0.2s;' onmouseover=\"this.style.background='#f5f5f5'\" onmouseout=\"this.style.background='white'\">
                <td style='padding:12px; border-right:1px solid #eee;'><code style='background:#f8f8f8; padding:2px 6px; border-radius:3px;'>{$naturalSpring->id}</code></td>
                <td style='padding:12px; border-right:1px solid #eee;'><a style='text-decoration: none; color: #2697bc; font-weight: bold; transition: color 0.2s;' href='/resources/natural-springs/{$naturalSpring->id}' onmouseover=\"this.style.color='#1a7594'\" onmouseout=\"this.style.color='#2697bc'\">{$naturalSpring->name}</a></td>
            </tr>";
        }

        $fields[] = Text::make('Risultati', function () use ($tableRows) {
            return "<div style='background:white; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.1); overflow:hidden; margin:10px 0;'>
                <table style='width:100%; border-collapse:collapse; background:white;'>
                    <thead>
                        <tr style='background:#f5f7fa;'>
                            <th style='padding:15px; text-align:left; color:#2697bc; font-weight:600; border-bottom:2px solid #eee;'>ID</th>
                            <th style='padding:15px; text-align:left; color:#2697bc; font-weight:600; border-bottom:2px solid #eee;'>Nome</th>
                        </tr>
                    </thead>
                    <tbody>" . implode('', $tableRows) . "</tbody>
                </table>
            </div>";
        })->asHtml()->onlyOnDetail();

        return $fields;
    }
}
