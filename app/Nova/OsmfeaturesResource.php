<?php

namespace App\Nova;

use App\Helpers\Osm2caiHelper;
use App\Nova\Filters\MissingOnOsmfeaturesFilter;
use App\Nova\Filters\OsmFilter;
use App\Nova\Filters\OsmtagsFilter;
use App\Nova\Filters\ScoreFilter;
use App\Nova\Filters\SourceFilter;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Wm\WmPackage\Nova\AbstractEcResource;
use Wm\WmPackage\Nova\Fields\FeatureCollectionMap\src\FeatureCollectionMap;

abstract class OsmfeaturesResource extends AbstractEcResource
{
    /**
     * Get the fields displayed by the resource.
     */
    public function fields(NovaRequest $request): array
    {
        $geometryField = FeatureCollectionMap::make(__('Geometry'), 'geometry')
            ->height(500)
            ->hideFromIndex();

        $fields = [
            ID::make()->sortable(),
            Text::make(__('Name'), 'name')->sortable(),
            DateTime::make(__('Created At'), 'created_at')->hideFromIndex(),
            DateTime::make(__('Updated At'), 'updated_at')->hideFromIndex(),
            Badge::make(__('Osmfeatures status'), 'osmfeatures_exists')
                ->map([
                    true => 'success',
                    false => 'danger',
                    null => 'warning',
                ])
                ->labels([
                    true => __('Present'),
                    false => __('Deleted from OSMFEATURES'),
                    null => __('Unknown'),
                ])
                ->sortable(),
            Text::make(__('Osmfeatures ID'), function () {
                if (! $this->osmfeatures_id) {
                    return '';
                }

                return Osm2caiHelper::getOpenstreetmapUrlAsHtml($this->osmfeatures_id);
            })->asHtml()->hideWhenCreating()->hideWhenUpdating(),
            Text::make(__('OSM Type'), 'osmfeatures_data->properties->osm_type'),
            DateTime::make(__('Osmfeatures updated at'), 'osmfeatures_updated_at')->sortable(),
            Code::make(__('Osmfeatures Data'), 'osmfeatures_data')
                ->json()
                ->language('php')
                ->resolveUsing(function ($value) {
                    return Osm2caiHelper::getOsmfeaturesDataForNovaDetail($value);
                }),
        ];

        return array_merge($fields, [$geometryField]);
    }

    /**
     * Get the filters available for the resource.
     */
    public function filters(NovaRequest $request): array
    {
        return [
            (new OsmFilter),
            (new MissingOnOsmfeaturesFilter),
            (new ScoreFilter),
            (new OsmtagsFilter('wikimedia_commons', 'WikiMedia')),
            (new OsmtagsFilter('wikipedia', 'Wikipedia')),
            (new OsmtagsFilter('wikidata', 'Wikidata')),
            (new OsmtagsFilter('website', 'Website')),
            (new SourceFilter),
        ];
    }

    /**
     * Get the cards available for the request.
     */
    public function cards(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     */
    public function lenses(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     */
    public function actions(NovaRequest $request): array
    {
        return [];
    }

    /**
     * Apply comprehensive search to the query.
     *
     * Handles common search fields for all OsmfeaturesResource children:
     * - osmfeatures_id (with or without OSM type prefix R/N/W)
     * - id (only if search term is completely numeric)
     * - name (partial match)
     *
     * SEARCH BEHAVIORS:
     * 1. "R19732" (with OSM type prefix) → searches ONLY in osmfeatures_id + name (NOT in id)
     * 2. "19732" (without prefix, numeric) → searches in id + osmfeatures_id + name
     * 3. Text → searches name + osmfeatures_id
     *
     * Child classes can override this method to add specific search logic,
     * but should call parent::applySearch() to include common fields.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     *
     * @phpstan-ignore-next-line
     */
    protected static function applySearch(Builder $query, string $search): Builder
    {
        $search = trim($search);
        $hasOsmPrefix = preg_match('/^([RNW])\s*(\d+)$/i', $search, $matches);
        $isNumeric = ctype_digit($search);

        // Ottieni i campi di ricerca dalla classe figlia (se definiti)
        $searchFields = static::$search ?? [];

        // Caso 1: prefisso OSM (R/N/W + numero)
        if ($hasOsmPrefix) {
            $osmId = strtoupper($matches[1]) . $matches[2];

            return $query->where(function ($q) use ($osmId, $search, $searchFields) {
                $q->where('osmfeatures_id', 'ilike', "{$osmId}%");

                // Aggiungi eventuali campi dichiarati in $search (inclusi id e name)
                static::addSearchFieldsConditions($q, $searchFields, $search, false);
            });
        }

        // Caso 2: solo numeri
        if ($isNumeric) {
            return $query->where(function ($q) use ($search, $searchFields, $isNumeric) {
                // osmfeatures_id con prefisso R/N/W (il formato è sempre [NWR][numero])
                $q->where('osmfeatures_id', 'ilike', "R{$search}%")
                    ->orWhere('osmfeatures_id', 'ilike', "N{$search}%")
                    ->orWhere('osmfeatures_id', 'ilike', "W{$search}%");

                // Aggiungi eventuali campi dichiarati in $search (inclusi id e name)
                static::addSearchFieldsConditions($q, $searchFields, $search, $isNumeric);
            });
        }

        // Caso 3: testo libero
        return $query->where(function ($q) use ($search, $searchFields) {
            // Aggiungi eventuali campi dichiarati in $search (inclusi id e name)
            static::addSearchFieldsConditions($q, $searchFields, $search, false);
        });
    }

    /**
     * Aggiunge condizioni di ricerca per i campi dichiarati in $search.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array  $searchFields
     * @param  string  $searchTerm
     * @param  bool  $isNumeric Se true, per il campo 'id' usa match esatto invece di ilike
     * @return void
     */
    protected static function addSearchFieldsConditions(Builder $query, array $searchFields, string $searchTerm, bool $isNumeric = false): void
    {
        foreach ($searchFields as $field) {
            // Ignora solo osmfeatures_id perché ha logica speciale gestita nei casi principali
            if ($field === 'osmfeatures_id') {
                continue;
            }

            // Verifica se è un campo JSON (contiene ->)
            if (strpos($field, '->') !== false) {
                // Campo JSON: converte osmfeatures_data->properties->ref in osmfeatures_data->'properties'->>'ref'
                $jsonPath = static::convertJsonFieldToPostgresSyntax($field);
                $query->orWhereRaw("{$jsonPath} ILIKE ?", ["%{$searchTerm}%"]);
            } else {
                // Campo normale
                // Per 'id' con ricerca numerica, usa match esatto invece di ilike
                if ($field === 'id' && $isNumeric) {
                    $query->orWhere($field, '=', (int) $searchTerm);
                } else {
                    $query->orWhere($field, 'ilike', "%{$searchTerm}%");
                }
            }
        }
    }

    /**
     * Converte un campo JSON da sintassi Laravel a sintassi PostgreSQL.
     * Esempio: osmfeatures_data->properties->ref diventa osmfeatures_data->'properties'->>'ref'
     *
     * @param  string  $field
     * @return string
     */
    protected static function convertJsonFieldToPostgresSyntax(string $field): string
    {
        $parts = explode('->', $field);
        $baseField = array_shift($parts);

        if (empty($parts)) {
            return $baseField;
        }

        // L'ultimo elemento usa ->> per ottenere il valore come testo
        $lastPart = array_pop($parts);
        $path = $baseField;

        // Aggiungi le parti intermedie con ->
        foreach ($parts as $part) {
            $path .= "->'{$part}'";
        }

        // L'ultima parte usa ->> per ottenere il testo
        $path .= "->>'{$lastPart}'";

        return $path;
    }

    public function getOsmfeaturesTabFields(): array
    {
        $props = fn() => $this->resource->osmfeatures_data['properties'] ?? [];

        return [
            Text::make(__('Osmfeatures ID (interno)'), 'osmfeatures_id'),
            Text::make(__('OSM (relation/way)'), function () use ($props) {
                $p = $props();
                $osmType = $p['osm_type'] ?? null;
                $osmId = $p['osm_id'] ?? null;
                if ($osmType === null || $osmId === null) {
                    return '—';
                }
                $osmRef = $osmType . (string) $osmId;

                return Osm2caiHelper::getOpenstreetmapUrlAsHtml($osmRef);
            })->asHtml(),
            Text::make(__('osm_api'), function () use ($props) {
                $url = $props()['osm_api'] ?? null;
                if (! $url) {
                    return '—';
                }

                return '<a href="' . e($url) . '" target="_blank" rel="noopener">' . e($url) . '</a>';
            })->asHtml(),
            Text::make(__('ref'), fn() => $props()['ref'] ?? '—'),
            Text::make(__('ref_REI'), fn() => $props()['ref_REI'] ?? '—'),
            Text::make(__('source_ref'), fn() => $props()['source_ref'] ?? '—'),
            Text::make(__('from'), fn() => $props()['from'] ?? '—'),
            Text::make(__('to'), fn() => $props()['to'] ?? '—'),
            Text::make(__('name'), fn() => $props()['name'] ?? '—'),
            Text::make(__('network'), fn() => $props()['network'] ?? '—'),
            Text::make(__('cai_scale'), fn() => $props()['cai_scale'] ?? '—'),
            Text::make(__('symbol'), fn() => $props()['symbol'] ?? '—'),
            Text::make(__('symbol_it'), fn() => $props()['symbol_it'] ?? '—'),
            Text::make(__('osmc_symbol'), fn() => $props()['osmc_symbol'] ?? '—'),
            Text::make(__('source'), fn() => $props()['source'] ?? '—'),
            Text::make(__('operator'), fn() => $props()['operator'] ?? '—'),
            Text::make(__('website'), function () use ($props) {
                $url = $props()['website'] ?? null;
                if (! $url) {
                    return '—';
                }

                return '<a href="' . e($url) . '" target="_blank" rel="noopener">' . e($url) . '</a>';
            })->asHtml(),
            Text::make(__('score'), function () use ($props) {
                $s = $props()['score'] ?? null;
                if ($s === null) {
                    return '—';
                }

                return Osm2caiHelper::getScoreAsStars((int) $s) . " ({$s})";
            })->asHtml(),
            Text::make(__('ascent'), fn() => $props()['ascent'] ?? '—'),
            Text::make(__('descent'), fn() => $props()['descent'] ?? '—'),
            Text::make(__('distance'), fn() => $props()['distance'] ?? '—'),
            Text::make(__('duration_forward'), fn() => $props()['duration_forward'] ?? '—'),
            Text::make(__('duration_backward'), fn() => $props()['duration_backward'] ?? '—'),
            Text::make(__('roundtrip'), fn() => ($props()['roundtrip'] ?? null) === true ? __('Yes') : (($props()['roundtrip'] ?? null) === false ? __('No') : '—')),
            Text::make(__('state'), fn() => $props()['state'] ?? '—'),
            Text::make(__('osm2cai_status'), fn() => $props()['osm2cai_status'] ?? '—'),
            Text::make(__('updated_at'), fn() => $props()['updated_at'] ?? '—'),
            Text::make(__('updated_at_osm'), fn() => $props()['updated_at_osm'] ?? '—'),
            Text::make(__('survey_date'), fn() => $props()['survey_date'] ?? '—'),
            Text::make(__('description'), fn() => $props()['description'] ?? '—'),
            Text::make(__('description_it'), fn() => $props()['description_it'] ?? '—'),
            Text::make(__('note'), fn() => $props()['note'] ?? '—'),
            Text::make(__('note_it'), fn() => $props()['note_it'] ?? '—'),
            Text::make(__('maintenance'), fn() => $props()['maintenance'] ?? '—'),
            Text::make(__('maintenance_it'), fn() => $props()['maintenance_it'] ?? '—'),
            Text::make(__('note_project_page'), fn() => $props()['note_project_page'] ?? '—'),
            Text::make(__('old_ref'), fn() => $props()['old_ref'] ?? '—'),
            Text::make(__('rwn_name'), fn() => $props()['rwn_name'] ?? '—'),
            Text::make(__('wikidata'), fn() => $props()['wikidata'] ?? '—'),
            Text::make(__('wikipedia'), fn() => $props()['wikipedia'] ?? '—'),
            Text::make(__('wikimedia_commons'), fn() => $props()['wikimedia_commons'] ?? '—'),
            Text::make(__('dem_enrichment'), fn() => $props()['dem_enrichment'] !== null ? json_encode($props()['dem_enrichment'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '—'),
            Code::make(__('osm_tags'), function () use ($props) {
                $tags = $props()['osm_tags'] ?? null;
                if (! is_array($tags)) {
                    return '';
                }

                return json_encode($tags, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            })->json()->onlyOnDetail(),
            Text::make(__('admin_areas'), function () use ($props) {
                $adminAreas = $props()['admin_areas']['admin_area'] ?? null;
                if (! is_array($adminAreas)) {
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
                $out = [];
                foreach ($adminAreas as $level => $items) {
                    if (! is_array($items)) {
                        continue;
                    }
                    $label = $labels[$level] ?? __('Livello') . ' ' . $level;
                    $names = array_map(fn($a) => ($a['name'] ?? '') . ' (' . ($a['osmfeatures_id'] ?? '') . ')', $items);
                    $out[] = '<strong>' . e($label) . '</strong>: ' . implode(', ', array_map('e', $names));
                }

                return implode('<br>', $out);
            })->asHtml()->onlyOnDetail(),
        ];
    }
}
