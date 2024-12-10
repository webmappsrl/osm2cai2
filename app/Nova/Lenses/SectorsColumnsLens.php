<?php

namespace App\Nova\Lenses;

use App\Nova\Sector;
use Laravel\Nova\Fields\ID;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Http\Requests\LensRequest;

class SectorsColumnsLens extends Lens
{

    /**
     * Get the query builder / paginator for the lens.
     *
     * @param  \Laravel\Nova\Http\Requests\LensRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return mixed
     */
    public static function query(LensRequest $request, $query)
    {
        return $request->withOrdering($request->withFilters(
            $query
        ));
    }

    /**
     * Get the fields available to the lens.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function fields(Request $request)
    {

        return [
            Text::make(__('Codice'), 'name')->sortable(),
            Text::make(__('Name'), 'human_name')
               ->sortable(),
            Text::make(__('Code'), 'code')->sortable(),
            Text::make(__('Responsabili'),'manager'),
            Number::make(__('Numero Atteso'),'num_expected'),
            Text::make(__('Full code'), 'full_code')->sortable(),
            Text::make(__('Region'), 'area_id', function ($value) {
                return $this->area->province->region->name ?? '';
            }),
            Text::make(__('Province'), 'area_id', function () {
                return $this->area->province->name;
            }),
            Text::make(__('Area'), 'area_id', function () {
                return $this->area->name;
            }),
        ];
    }

    /**
     * Get the cards available on the lens.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function cards(Request $request)
    {
        return [];
    }

    /**
     * Get the filters available for the lens.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function filters(Request $request)
    {
        return [];
    }

    /**
     * Get the actions available on the lens.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function actions(Request $request)
    {
        return parent::actions($request);
    }

}


class NoResponsabileSectorsColumnsLens extends SectorsColumnsLens {

    public function name(){
        return 'Senza colonna resposabile';
    }
    public function uriKey(){
        return 'no-resposabile';
    }
    public function fields( $request )
    {
        $fields = collect( parent::fields($request) );
        return $fields->filter( function( $e ) {
            return $e->attribute != 'manager';
        }  )->all();
    }

}

class NoNameSectorsColumnsLens extends SectorsColumnsLens {

    public function name(){
        return 'Senza colonna nome';
    }
    public function uriKey(){
        return 'no-name';
    }
    public function fields( $request )
    {
        $fields = collect( parent::fields($request) );
        return $fields->filter( function( $e ) {
            return $e->attribute != 'name' && $e->attribute != 'human_name';
        }  )->all();
    }

}



class NoNumExpectedColumnsLens extends SectorsColumnsLens {

    public function name(){
        return 'Senza colonna numero atteso';
    }
    public function uriKey(){
        return 'no-num-expected';
    }
    public function fields( $request )
    {
        $fields = collect( parent::fields($request) );
        return $fields->filter( function( $e ) {
            return $e->attribute != 'num_expected';
        }  )->all();
    }

}
