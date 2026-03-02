<?php

namespace App\Nova;

use App\Models\SIPoi as SIPoiModel;
use Ebess\AdvancedNovaMediaLibrary\Fields\Images;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Tabs\Tab;
use Wm\WmPackage\Nova\Fields\PropertiesPanel;
use Wm\WmPackage\Nova\EcTrack as EcTrackResource;

class SiPoi extends EcPoi
{
    /**
     * Il model associato alla risorsa.
     *
     * @var class-string<SIPoiModel>
     */
    public static $model = SIPoiModel::class;

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
            Text::make(__('Data'), 'properties->sicai->data')->readonly(),
            Text::make(__('DB Table'), 'properties->sicai->dbtable')->readonly(),
            Text::make(__('Tappa 01'), 'properties->sicai->tappa01')->readonly(),
            Text::make(__('Tappa 02'), 'properties->sicai->tappa02')->readonly(),
            Text::make(__('Tappa 03'), 'properties->sicai->tappa03')->readonly(),
            Text::make(__('Regione'), 'properties->sicai->Regione')->readonly(),
            Text::make(__('Nome struttura'), 'properties->sicai->nome')->readonly(),
            Text::make(__('Denominazione'), 'properties->sicai->denominazione')->readonly(),
            Text::make(__('Note'), 'properties->sicai->note')->readonly(),
            Text::make(__('Email'), 'properties->sicai->email')->readonly(),
            Text::make(__('Telefono'), 'properties->sicai->phone')->readonly(),
            Text::make(__('Tourism'), 'properties->sicai->tourism')->readonly(),
            Text::make(__('Website'), 'properties->sicai->website')->readonly(),
            Text::make(__('Immagine (path)'), 'properties->sicai->immagine')->readonly(),
            Text::make(__('Foto 02'), 'properties->sicai->foto02')->readonly(),
            Text::make(__('Foto 03'), 'properties->sicai->foto03')->readonly(),
            Text::make(__('Foto 04'), 'properties->sicai->foto04')->readonly(),
            Text::make(__('Foto 05'), 'properties->sicai->foto05')->readonly(),
            Text::make(__('Città'), 'properties->sicai->addr:city')->readonly(),
            Text::make(__('Via'), 'properties->sicai->addr:street')->readonly(),
            Text::make(__('Numero civico'), 'properties->sicai->addr:housenumber')->readonly(),
            Text::make(__('Materiale'), 'properties->sicai->materiale')->readonly(),
            Text::make(__('Situazione'), 'properties->sicai->situazione')->readonly(),
            Text::make(__('Operatore'), 'properties->sicai->operator')->readonly(),
            Text::make(__('Rifugio CAI'), 'properties->sicai->rifugio_cai')->readonly(),
            Text::make(__('Orari apertura'), 'properties->sicai->opening_hours')->readonly(),
            Text::make(__('Source key'), 'properties->sicai->source_key')->readonly(),
            Boolean::make(__('Punto accoglienza ufficiale'), 'properties->sicai->pt_accoglienza')->readonly(),
        ];
    }
}
