<?php

namespace App\Nova;

use App\Enums\SicaiSituazioneEnum;
use App\Models\SiPoi as SiPoiModel;
use Ebess\AdvancedNovaMediaLibrary\Fields\Images;
use Illuminate\Database\Eloquent\Builder;
use Kongulov\NovaTabTranslatable\NovaTabTranslatable;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Tabs\Tab;
use Marshmallow\Tiptap\Tiptap;
use Wm\MapPoint\MapPoint;
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
            Text::make(__('Data'), 'properties->sicai->data')->readonly(),
            Text::make(__('Tappa 01'), 'properties->sicai->tappa01')->readonly(),
            Text::make(__('Tappa 02'), 'properties->sicai->tappa02')->readonly(),
            Text::make(__('Tappa 03'), 'properties->sicai->tappa03')->readonly(),
            Text::make(__('Regione'), 'properties->sicai->Regione')->readonly(),
            Text::make(__('Nome struttura'), 'properties->sicai->nome')->readonly(),
            Text::make(__('Denominazione'), 'properties->sicai->denominazione')->readonly(),
            Text::make(__('Note'), 'properties->sicai->note')->readonly(),
            Text::make(__('Tourism'), 'properties->sicai->tourism')->readonly(),
            Text::make(__('Materiale'), 'properties->sicai->materiale')->readonly(),
            Select::make(__('Situazione'), 'properties->sicai->situazione')
                ->options($this->situazioneOptions())
                ->nullable()
                ->displayUsingLabels(),
            Text::make(__('Operatore'), 'properties->sicai->operator')->readonly(),
            Text::make(__('Rifugio CAI'), 'properties->sicai->rifugio_cai')->readonly(),
            Boolean::make(__('Punto accoglienza ufficiale'), 'properties->sicai->pt_accoglienza')->readonly(),
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

        $fields[] = Tab::group(__('Details'), [
            Tab::make(__('SICAI'), $this->getSicaiTabFields()),
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
        $fields[] = Tab::group(__('Details'), [
            Tab::make(__('SICAI'), $this->getSicaiTabFields()),
            Tab::make(__('Info'), $this->getInfoTabFields()),
        ]);
        return $fields;
    }
}
