<?php

namespace App\Nova;

use App\Nova\AbstractUgc;
use App\Nova\Actions\DownloadUgcCsv;
use App\Nova\Filters\UgcFormIdFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Wm\MapPoint\MapPoint;

class UgcPoi extends AbstractUgc
{
    /**
     * The model the resource corresponds to.
     *
     * @var string
     */
    public static $model = \App\Models\UgcPoi::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public function title()
    {
        return $this->raw_data['title'] ?? $this->name ?? $this->id;
    }

    public static function label()
    {
        $label = 'Poi';

        return __($label);
    }

    /**
     * Apply search filters to the query
     *
     * Searches for matches in:
     * - POI name
     * - POI ID
     * - Associated user's name
     * - Associated user's email
     *
     * @param \Illuminate\Database\Eloquent\Builder $query The query builder instance
     * @param string $search The search term to filter by
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function applySearch($query, $search)
    {
        return $query->where(function ($query) use ($search) {
            $query->where('name', 'like', '%'.$search.'%')
                ->orWhere('id', 'like', '%'.$search.'%')
                ->orWhereHas('user', function ($query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
        });
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @param Request $request
     * @return array
     */
    public function fields(Request $request)
    {
        if ($request->isCreateOrAttachRequest()) {
            $formIdOptions = $this->getFormIdOptions();

            return [
                Select::make('Form ID', 'form_id')
                    ->options($formIdOptions)
                    ->rules('required')
                    ->help('Seleziona il tipo di UGC che vuoi creare. Dopo il salvataggio, potrai inserire tutti i dettagli.'),
            ];
        }

        $parentFields = parent::fields($request);

        if ($this->form_id == 'poi') {
            array_splice($parentFields, array_search('user', array_column($parentFields, 'name')), 0, [Text::make('Poi Type', 'raw_data->waypointtype')->onlyOnDetail()]);
        }

        return array_merge($parentFields, $this->additionalFields($request));
    }

    public function additionalFields(Request $request)
    {
        return [
            Text::make('Form ID', 'form_id')->resolveUsing(function ($value) {
                if ($this->raw_data and isset($this->raw_data['id'])) {
                    return $this->raw_data['id'];
                } else {
                    return $value;
                }
            })
                ->hideWhenCreating()
                ->hideWhenUpdating(),
            MapPoint::make('geometry')->withMeta([
                'center' => [43.7125, 10.4013],
                'attribution' => '<a href="https://webmapp.it/">Webmapp</a> contributors',
                'tiles' => 'https://api.webmapp.it/tiles/{z}/{x}/{y}.png',
                'minZoom' => 8,
                'maxZoom' => 14,
                'defaultZoom' => 10,
                'defaultCenter' => [43.7125, 10.4013],
            ])->hideFromIndex(),
            $this->getCodeField('Form data', ['id', 'form_id', 'waypointtype', 'key', 'date', 'title']),
            $this->getCodeField('Device data', ['position', 'displayPosition', 'city', 'date']),
            $this->getCodeField('Nominatim'),
            $this->getCodeField('Raw data'),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param Request $request
     * @return array
     */
    public function cards(Request $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param Request $request
     * @return array
     */
    public function filters(Request $request)
    {
        $parentFilters = parent::filters($request);

        return array_merge($parentFilters, [
            (new UgcFormIdFilter()),
        ]);
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param Request $request
     * @return array
     */
    public function lenses(Request $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param Request $request
     * @return array
     */
    public function actions(Request $request)
    {
        $parentActions = parent::actions($request);
        $specificActions = [
            (new DownloadUgcCsv($this)),
        ];

        return array_merge($parentActions, $specificActions);
    }

    /**
     * Determines if the user is authorized to create a new resource.
     *
     * @param Request $request The current HTTP request
     * @return bool Always returns true, allowing all users to create new resources
     */
    public static function authorizedToCreate(Request $request)
    {
        return true;
    }

    /**
     * Redirects the user after creating a new resource.
     *
     * @param Request $request The current HTTP request
     * @param \Laravel\Nova\Resource $resource The newly created resource
     * @return string The URL to redirect the user to after resource creation
     */
    public static function redirectAfterCreate(Request $request, $resource)
    {
        return '/resources/ugc-pois/'.$resource->id.'/edit';
    }

    /**
     * Get the options for the form_id field.
     *
     * This method retrieves configurations from a config file,
     * makes HTTP requests to fetch form data, and builds an array
     * of options for the form_id field. The result is cached for
     * one hour to improve performance.
     *
     * @return array An associative array with form IDs as keys and labels as values
     */
    protected function getFormIdOptions()
    {
        return Cache::remember('form_id_options', 3600, function () {
            $configs = config('geohub.configs');
            $formIdOptions = [];

            foreach ($configs as $configUrl) {
                $response = Http::get($configUrl);
                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['APP']['poi_acquisition_form'])) {
                        foreach ($data['APP']['poi_acquisition_form'] as $form) {
                            $formIdOptions[$form['id']] = $form['label']['it'] ?? $form['id'];
                        }
                    }
                }
            }

            return $formIdOptions;
        });
    }

    public static function getExportFields(): array
    {
        return [
            'id' => 'ID',
            'user->name' => 'Nome utente',
            'user->email' => 'Email utente',
            'registered_at' => 'Data di acquisizione',
            'raw_data->latitude' => 'Latitudine',
            'raw_data->longitude' => 'Longitudine',
            'validated' => 'Stato di validazione',
            'validation_date' => 'Data di validazione',
            'app_id' => 'App ID',
        ];
    }
}
