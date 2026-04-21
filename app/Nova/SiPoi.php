<?php

namespace App\Nova;

use App\Enums\SicaiSituazioneEnum;
use App\Models\SiPoi as SiPoiModel;
use Wm\WmPackage\Nova\Actions\RegenerateEcPoiTaxonomyWhere;
use Ebess\AdvancedNovaMediaLibrary\Fields\Images;
use Illuminate\Database\Eloquent\Builder;
use Kongulov\NovaTabTranslatable\NovaTabTranslatable;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\MorphToMany;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Tabs\Tab;
use Marshmallow\Tiptap\Tiptap;
use Wm\MapPoint\MapPoint;
use Wm\WmPackage\Nova\TaxonomyPoiType;
use Wm\WmPackage\Nova\Fields\PropertiesPanel;
use Wm\WmPackage\Nova\Fields\FeatureCollectionMap\src\FeatureCollectionMap;

class SiPoi extends EcPoi
{
    /**
     * Il model associato alla risorsa.
     *
     * @var class-string<SiPoiModel>
     */
    public static $model = SiPoiModel::class;

    /**
     * Etichetta plurale della risorsa.
     */
    public static function label(): string
    {
        return __('SI Pois');
    }

    /**
     * Etichetta singolare della risorsa.
     */
    public static function singularLabel(): string
    {
        return __('SI Poi');
    }

    /**
     * Limita l’index agli EC provenienti da pt_accoglienza_unofficial (app_id = 2).
     */
    public static function indexQuery(NovaRequest $request, $query): Builder
    {
        /** @var Builder $query */
        $query = parent::indexQuery($request, $query);

        return $query
            ->where('app_id', 2)
            ->whereRaw("(properties->'sicai'->>'dbtable') = 'pt_accoglienza_unofficial'");
    }

    /**
     * Campi mostrati nelle varie viste.
     * Riusa i campi della risorsa EcPoi e aggiunge il pannello SICAI.
     */
    public function fields(NovaRequest $request): array
    {

        if ($request->isResourceIndexRequest()) {
            return $this->sicaiIndexFields($request);
        }
        if ($request->isResourceDetailRequest()) {
            return $this->sicaiDetailFields($request);
        }

        return $this->sicaiEditFields($request);
        $fields = parent::fields($request);

        // Rimuove il pannello generico "Properties" ereditato da AbstractGeometryResource
        // e il campo EcTracks originale, che verrà riaggiunto in posizione controllata
        $fields = array_values(array_filter($fields, function ($field) {
            if ($field instanceof PropertiesPanel && $field->name === __('Properties')) {
                return false;
            }

            if ($field instanceof BelongsToMany && $field->name === 'EcTracks') {
                return false;
            }

            return true;
        }));
        $fields[] = BelongsToMany::make(__('SI Hiking Routes'), 'siHikingRoutes', SiHikingRoute::class);
        // Aggiunge un gruppo di tab "Details" con la tab SICAI (come per SiHikingRoute / SiMTBRoute)
        $fields[] = Tab::group(__('Details'), [
            Tab::make(__('SICAI'), $this->getSicaiTabFields()),
        ]);

        return $fields;
    }

    /**
     * Campi per la tab delle sicai properties del POI.
     *
     * I path puntano a properties->sicai->* dove:
     * - 'dbtable' è impostato in import (pt_accoglienza_unofficial)
     * - tappa01/02/03 sono usate per le relazioni con le HikingRoute
     * - vengono esposti anche i campi descrittivi e di contatto.
     */
    public function getSicaiTabFields(): array
    {
        return [
            ID::make()->onlyOnDetail(),
            Text::make(__('CIN'), 'properties->sicai->CIN'),
            Text::make(__('Data'), 'properties->sicai->data'),
            Text::make(__('Tappa 01'), 'properties->sicai->tappa01'),
            Text::make(__('Tappa 02'), 'properties->sicai->tappa02'),
            Text::make(__('Tappa 03'), 'properties->sicai->tappa03'),
            Text::make(__('Regione'), 'properties->sicai->Regione')
                ->hideFromDetail()
                ->hideWhenCreating()
                ->hideWhenUpdating(),
            Text::make(__('Nome struttura'), 'properties->sicai->nome')
                ->hideFromDetail()
                ->hideWhenCreating()
                ->hideWhenUpdating(),
            Text::make(__('Denominazione'), 'properties->sicai->denominazione')
                ->hideFromDetail()
                ->hideWhenCreating()
                ->hideWhenUpdating(),
            Text::make(__('Note'), 'properties->sicai->note')
                ->hideFromDetail()
                ->hideWhenCreating()
                ->hideWhenUpdating(),
            Text::make(__('Tourism'), 'properties->sicai->tourism')
                ->hideFromDetail()
                ->hideWhenCreating()
                ->hideWhenUpdating(),
            Text::make(__('Materiale'), 'properties->sicai->materiale'),
            Select::make(__('Situazione'), 'properties->sicai->situazione')
                ->options($this->situazioneOptions())
                ->nullable()
                ->displayUsingLabels(),
            Text::make(__('Operatore'), 'properties->sicai->operator'),
            Text::make(__('Rifugio CAI'), 'properties->sicai->rifugio_cai'),
            Boolean::make(__('Punto accoglienza ufficiale'), 'properties->sicai->pt_accoglienza'),
        ];
    }

    public function situazioneOptions(): array
    {
        return collect(SicaiSituazioneEnum::cases())->mapWithKeys(fn($c) => [$c->value => $c->value])->all();
    }


    public function sicaiIndexFields(): array
    {
        $fields = [];

        $fields[] = ID::make();
        $fields[] = Text::make(__('Name'), 'name');
        $fields[] = Images::make(__('Image'), 'default');

        return $fields;
    }

    public function sicaiDetailFields(): array
    {
        $fields = [];

        $fields[] = Text::make('id', 'id');
        $fields[] = Boolean::make(__('Global'), 'global');
        $fields[] = NovaTabTranslatable::make([Text::make('name', 'name'), Tiptap::make(__('description'), 'properties->description')]);
        $fields[] = FeatureCollectionMap::make(__('Geometry'), 'geometry');
        $fields[] = Images::make(__('Image'), 'default');
        $fields[] = BelongsToMany::make(__('SI Hiking Routes'), 'siHikingRoutes', SiHikingRoute::class);
        $fields[] = MorphToMany::make(__('Taxonomy Poi Types'), 'taxonomyPoiTypes', TaxonomyPoiType::class)
            ->display('name')
            ->help(__('Tipologie di POI associate a questo punto di interesse'));

        $fields[] = Tab::group(__('Details'), [
            Tab::make(__('SICAI'), $this->getSicaiTabFields()),
            Tab::make(__('OSMFEATURES'), $this->getOsmfeaturesTabFields()),
            Tab::make(__('DEM'), $this->getDemTabFields()),
            Tab::make(__('Info'), $this->getInfoTabFields()),
        ]);
        return $fields;
    }

    public function sicaiEditFields(): array
    {
        $fields = [];
        $fields[] = Boolean::make(__('Global'), 'global');
        $fields[] = Text::make(__('Name'), 'name');
        $fields[] = Images::make(__('Image'), 'default');
        $fields[] = MapPoint::make(__('Geometry'), 'geometry');
        $fields[] = BelongsToMany::make(__('SI Hiking Routes'), 'siHikingRoutes', SiHikingRoute::class);
        $fields[] = MorphToMany::make(__('Taxonomy Poi Types'), 'taxonomyPoiTypes', TaxonomyPoiType::class)
            ->display('name')
            ->help(__('Tipologie di POI associate a questo punto di interesse'));
        $fields[] = Tab::group(__('Details'), [
            Tab::make(__('SICAI'), $this->getSicaiTabFields()),
            Tab::make(__('Info'), $this->getInfoTabFields()),
        ]);
        return $fields;
    }

    public function getOsmfeaturesTabFields(): array
    {
        return [
            Text::make(__('admin_areas'), function () {
                $taxonomyWhere = $this->resource->properties['taxonomy_where'] ?? null;
                if (! is_array($taxonomyWhere) || empty($taxonomyWhere)) {
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

                $groupedByLevel = [];
                foreach ($taxonomyWhere as $osmfeaturesId => $translations) {
                    if (! is_array($translations)) {
                        continue;
                    }

                    $level = (string) ($translations['_admin_level'] ?? '');
                    if ($level === '') {
                        $level = 'unknown';
                    }

                    $name = $translations['it'] ?? $translations['en'] ?? null;
                    if (! $name) {
                        continue;
                    }

                    $groupedByLevel[$level][] = [
                        'name' => $name,
                        'osmfeatures_id' => $osmfeaturesId,
                    ];
                }

                if (empty($groupedByLevel)) {
                    return '—';
                }

                $preferredOrder = ['2', '4', '6', '7', '8', '10', 'unknown'];
                uksort($groupedByLevel, function ($a, $b) use ($preferredOrder) {
                    $ai = array_search((string) $a, $preferredOrder, true);
                    $bi = array_search((string) $b, $preferredOrder, true);
                    $ai = $ai === false ? 999 : $ai;
                    $bi = $bi === false ? 999 : $bi;

                    return $ai <=> $bi;
                });

                $out = [];
                foreach ($groupedByLevel as $level => $items) {
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
            Text::make(__('Elevation (ele)'), function () {
                $properties = $this->resource->properties ?? [];
                $ele = $properties['ele'] ?? null;

                return $ele !== null ? (string) $ele : '—';
            })->onlyOnDetail(),
        ];
    }

    /**
     * Azioni disponibili per i SiPoi.
     */
    public function actions(NovaRequest $request): array
    {
        $actions = parent::actions($request);

        $actions[] = new RegenerateEcPoiTaxonomyWhere();

        return $actions;
    }
}
