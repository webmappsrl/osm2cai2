<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Translatable\HasTranslations;
use Wm\WmPackage\Models\Abstracts\Polygon;
use Wm\WmPackage\Nova\Fields\FeatureCollectionMap\src\FeatureCollectionMapTrait;

class SignageProject extends Polygon
{
    use FeatureCollectionMapTrait, HasTranslations;

    // Costanti per lo stile dei punti (pali) sulla mappa
    protected const POINT_STROKE_COLOR = 'rgb(255, 255, 255)';

    protected const POINT_STROKE_WIDTH = 2;

    protected const POINT_FILL_COLOR = 'rgba(255, 0, 0, 0.8)';

    protected const CHECKPOINT_FILL_COLOR = 'rgb(255, 160, 0)';

    protected const POINT_RADIUS = 4;

    protected const CHECKPOINT_RADIUS = 6;

    protected $table = 'signage_projects';

    public array $translatable = ['name'];

    protected $fillable = [
        'name',
        'description',
        'properties',
        'geometry',
        'app_id',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        // Aggiungi solo gli eventi necessari
        static::creating(function ($model) {
            if (is_null($model->properties)) {
                $model->properties = [];
            }

            if (Auth::check() && empty($model->user_id)) {
                $model->user_id = Auth::id();
            }

            // Imposta app_id a 1 se non è già impostato (sempre 1, stessa app delle hiking routes)
            if (empty($model->app_id)) {
                $model->app_id = 1;
            }
        });

        // Invalida la cache quando il progetto viene aggiornato (potrebbe aver cambiato geometria)
        static::updated(function ($model) {
            $model->clearFeatureCollectionMapCache();
        });

        // Invalida la cache quando il progetto viene eliminato
        static::deleted(function ($model) {
            $model->clearFeatureCollectionMapCache();
        });
    }

    /**
     * Get the description attribute from properties.
     */
    public function getDescriptionAttribute()
    {
        $properties = $this->properties ?? [];
        return $properties['description'] ?? null;
    }

    /**
     * Set the description attribute in properties.
     */
    public function setDescriptionAttribute($value)
    {
        // Recupera properties come array (il cast lo gestisce automaticamente)
        // Se properties non è ancora stato castato, recuperalo dagli attributes
        if (isset($this->attributes['properties'])) {
            $properties = is_array($this->attributes['properties'])
                ? $this->attributes['properties']
                : (json_decode($this->attributes['properties'], true) ?? []);
        } else {
            $properties = $this->properties ?? [];
        }

        if ($value !== null && $value !== '') {
            $properties['description'] = $value;
        } else {
            unset($properties['description']);
        }

        // Imposta properties come array, Laravel gestirà il cast automaticamente quando salva
        $this->attributes['properties'] = $properties;
    }

    /**
     * Get the user that owns this signage project.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the hiking routes associated with this signage project.
     * Usa la tabella polimorfica signage_projectables.
     * Le hiking routes sono sempre dell'app 1.
     */
    public function hikingRoutes(): MorphToMany
    {
        return $this->morphedByMany(HikingRoute::class, 'signage_projectable')
            ->using(SignageProjectable::class)
            ->where('app_id', 1);
    }


    /**
     * Get all poles for multiple hiking routes in a SINGLE batch query.
     * OTTIMIZZAZIONE CRITICA: Riduce da N query a 1 query batch.
     * 
     * Usa una singola query con JOIN e ST_DWithin per trovare tutti i poles
     * che appartengono a qualsiasi delle hiking routes specificate.
     *
     * @param array $hikingRouteIds Array di ID delle hiking routes
     * @param float $bufferDistance Distanza del buffer in metri (default: 10m)
     * @return array Mappa hiking_route_id => Collection<Poles>
     */
    private function getAllPolesForHikingRoutes(array $hikingRouteIds, float $bufferDistance = 15): array
    {
        if (empty($hikingRouteIds)) {
            return [];
        }

        // Inizializza la mappa risultato
        $polesByHikingRoute = [];
        foreach ($hikingRouteIds as $hrId) {
            $polesByHikingRoute[$hrId] = collect();
        }

        try {
            // STEP 1: Carica tutte le geometrie delle hiking routes in una singola query
            $geometries = DB::table('hiking_routes')
                ->whereIn('id', $hikingRouteIds)
                ->whereNotNull('geometry')
                ->select('id', DB::raw('ST_AsGeoJSON(geometry) as geometry'))
                ->get();

            if ($geometries->isEmpty()) {
                return $polesByHikingRoute;
            }

            // STEP 2: OTTIMIZZAZIONE CRITICA - Crea una geometria unificata con ST_Union
            // Invece di 695 condizioni OR, uniamo tutte le geometrie delle hiking routes
            // e facciamo una singola query ST_DWithin contro la geometria unificata
            // Questo è MOLTO più efficiente perché PostgreSQL può usare gli indici spaziali meglio

            // Crea una geometria unificata che contiene tutte le hiking routes
            $geometryIds = $geometries->pluck('id')->toArray();
            $unifiedGeometry = DB::table('hiking_routes')
                ->whereIn('id', $geometryIds)
                ->whereNotNull('geometry')
                ->selectRaw('ST_AsGeoJSON(ST_Union(geometry::geometry)) as unified_geom')
                ->value('unified_geom');

            if (!$unifiedGeometry) {
                return $polesByHikingRoute;
            }

            // SINGOLA QUERY - Trova tutti i poles che sono nel buffer della geometria unificata
            // OTTIMIZZAZIONE: Carica solo i dati essenziali (id, name, ref, properties, osmfeatures_data)
            // Filtra solo i poles che esistono su osmfeatures
            $allPoles = DB::table('poles')
                ->select('poles.id', 'poles.name', 'poles.ref', 'poles.properties', 'poles.osmfeatures_data')
                ->where('osmfeatures_exists', false)
                ->whereRaw(
                    'ST_DWithin(poles.geometry, ST_GeomFromGeoJSON(?)::geography, ?)',
                    [$unifiedGeometry, $bufferDistance]
                )
                ->get()
                ->keyBy('id');

            // STEP 3: SINGOLA QUERY BATCH - Determina l'appartenenza usando CROSS JOIN con WHERE
            // COLLO DI BOTTIGLIA RISOLTO: Invece di 695 query separate, usa una singola query
            // che restituisce direttamente (pole_id, hiking_route_id) per tutte le combinazioni
            // PostgreSQL ottimizzerà il CROSS JOIN usando gli indici spaziali nella WHERE clause

            $poleIds = $allPoles->pluck('id')->toArray();

            if (!empty($poleIds) && !$geometries->isEmpty()) {
                $geometryIds = $geometries->pluck('id')->toArray();

                // SINGOLA QUERY: Trova tutte le combinazioni (pole_id, hiking_route_id) in una volta
                // CROSS JOIN + WHERE con ST_DWithin è più efficiente di 695 query separate
                // PostgreSQL userà gli indici spaziali per ottimizzare
                $sql = "
                    SELECT DISTINCT p.id as pole_id, hr.id as hiking_route_id
                    FROM poles p
                    CROSS JOIN hiking_routes hr
                    WHERE p.id = ANY(?)
                    AND hr.id = ANY(?)
                    AND hr.geometry IS NOT NULL
                    AND ST_DWithin(p.geometry, hr.geometry::geography, ?)
                ";

                $belongings = DB::select($sql, [
                    '{' . implode(',', $poleIds) . '}',       // Array PostgreSQL degli ID poles
                    '{' . implode(',', $geometryIds) . '}',   // Array PostgreSQL degli ID hiking routes
                    $bufferDistance
                ]);

                // Raggruppa i poles per hiking route
                foreach ($belongings as $belonging) {
                    $hrId = (int) $belonging->hiking_route_id;
                    $poleId = (int) $belonging->pole_id;

                    if (!isset($polesByHikingRoute[$hrId])) {
                        $polesByHikingRoute[$hrId] = collect();
                    }

                    $pole = $allPoles->get($poleId);
                    if ($pole) {
                        // Crea un oggetto simile a Poles per compatibilità
                        $poleObj = new \stdClass();
                        $poleObj->id = $pole->id;
                        $poleObj->name = $pole->name;
                        $poleObj->ref = $pole->ref;
                        $poleObj->properties = is_string($pole->properties) ? json_decode($pole->properties, true) : $pole->properties;
                        $poleObj->osmfeatures_data = is_string($pole->osmfeatures_data) ? json_decode($pole->osmfeatures_data, true) : $pole->osmfeatures_data;

                        $poleExists = $polesByHikingRoute[$hrId]->first(function ($p) use ($poleId) {
                            return $p->id == $poleId;
                        });

                        if (!$poleExists) {
                            $polesByHikingRoute[$hrId]->push($poleObj);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Fallback al metodo precedente in caso di errore
            Log::warning('Error in optimized getAllPolesForHikingRoutes, falling back', [
                'error' => $e->getMessage(),
                'hiking_route_ids_count' => count($hikingRouteIds),
            ]);

            // Carica tutte le geometrie delle hiking routes in una singola query
            $geometries = DB::table('hiking_routes')
                ->whereIn('id', $hikingRouteIds)
                ->whereNotNull('geometry')
                ->select('id', DB::raw('ST_AsGeoJSON(geometry) as geometry'))
                ->get()
                ->keyBy('id');

            if (!$geometries->isEmpty()) {
                foreach ($geometries as $hrId => $geom) {
                    try {
                        $poles = Poles::select('poles.*')
                            ->whereRaw(
                                'ST_DWithin(poles.geometry, ST_GeomFromGeoJSON(?)::geography, ?)',
                                [$geom->geometry, $bufferDistance]
                            )
                            ->get();

                        $polesByHikingRoute[$hrId] = $poles;
                    } catch (\Exception $e2) {
                        Log::warning("Error loading poles for hiking route {$hrId} (fallback)", [
                            'error' => $e2->getMessage(),
                        ]);
                        $polesByHikingRoute[$hrId] = collect();
                    }
                }
            }
        }

        return $polesByHikingRoute;
    }

    /**
     * Carica solo i poles checkpoint per le hiking routes specificate.
     * OTTIMIZZAZIONE: Per progetti molto grandi, carica solo i poles checkpoint invece di tutti i poles.
     * Questo riduce drasticamente il numero di poles caricati e rende la mappa utilizzabile.
     *
     * @param array $hikingRouteIds Array di ID delle hiking routes
     * @param array $hikingRoutesProcessed Array con i dati delle hiking routes (per estrarre checkpoint)
     * @param float $bufferDistance Distanza del buffer in metri (default: 10m)
     * @return array Mappa hiking_route_id => Collection<Poles>
     */
    private function getCheckpointPolesForHikingRoutes(array $hikingRouteIds, array $hikingRoutesProcessed, float $bufferDistance = 10): array
    {
        if (empty($hikingRouteIds)) {
            return [];
        }

        // Inizializza la mappa risultato
        $polesByHikingRoute = [];
        foreach ($hikingRouteIds as $hrId) {
            $polesByHikingRoute[$hrId] = collect();
        }

        try {
            // Raccogli tutti gli ID dei poles checkpoint da tutte le hiking routes
            $allCheckpointPoleIds = [];
            foreach ($hikingRoutesProcessed as $hrData) {
                $checkpointIds = $hrData['properties']['signage']['checkpoint'] ?? [];
                if (!empty($checkpointIds)) {
                    $allCheckpointPoleIds = array_merge($allCheckpointPoleIds, $checkpointIds);
                }
            }
            $allCheckpointPoleIds = array_unique($allCheckpointPoleIds);

            if (empty($allCheckpointPoleIds)) {
                return $polesByHikingRoute;
            }

            // Carica solo i poles checkpoint (molto più veloce)
            $checkpointPoles = DB::table('poles')
                ->whereIn('id', $allCheckpointPoleIds)
                ->select('poles.id', 'poles.name', 'poles.ref', 'poles.properties', 'poles.osmfeatures_data')
                ->get()
                ->keyBy('id');

            // Per ogni hiking route, assegna i poles checkpoint che le appartengono
            foreach ($hikingRoutesProcessed as $hrData) {
                $hrId = $hrData['id'];
                $checkpointIds = $hrData['properties']['signage']['checkpoint'] ?? [];

                foreach ($checkpointIds as $poleId) {
                    $pole = $checkpointPoles->get($poleId);
                    if ($pole) {
                        // Crea un oggetto simile a Poles per compatibilità
                        $poleObj = new \stdClass();
                        $poleObj->id = $pole->id;
                        $poleObj->name = $pole->name;
                        $poleObj->ref = $pole->ref;
                        $poleObj->properties = is_string($pole->properties) ? json_decode($pole->properties, true) : $pole->properties;
                        $poleObj->osmfeatures_data = is_string($pole->osmfeatures_data) ? json_decode($pole->osmfeatures_data, true) : $pole->osmfeatures_data;

                        $polesByHikingRoute[$hrId]->push($poleObj);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Error in getCheckpointPolesForHikingRoutes', [
                'error' => $e->getMessage(),
                'hiking_route_ids_count' => count($hikingRouteIds),
            ]);
        }

        return $polesByHikingRoute;
    }

    /**
     * Converte tutte le geometrie dei poles da WKB a GeoJSON in una singola query batch.
     * OTTIMIZZAZIONE CRITICA: Elimina query N+1 convertendo tutte le geometrie in una volta.
     * 
     * @param array $poleIds Array di ID dei poles
     * @return array Mappa pole_id => geojson_array
     */
    private function getPolesGeoJsonBatch(array $poleIds): array
    {
        if (empty($poleIds)) {
            return [];
        }

        try {
            // Singola query per convertire tutte le geometrie
            // Cast geometry::geometry necessario perché il campo è geography ma ST_AsGeoJSON richiede geometry
            $results = DB::table('poles')
                ->whereIn('id', $poleIds)
                ->whereNotNull('geometry')
                ->select('id', DB::raw('ST_AsGeoJSON(geometry::geometry) as geojson'))
                ->get();

            // Crea mappa id => geojson_array
            $geoJsonMap = [];
            foreach ($results as $row) {
                if ($row->geojson) {
                    $decoded = json_decode($row->geojson, true);
                    if ($decoded !== null) {
                        $geoJsonMap[$row->id] = $decoded;
                    }
                }
            }

            return $geoJsonMap;
        } catch (\Exception $e) {
            Log::warning('Error in getPolesGeoJsonBatch', [
                'pole_ids_count' => count($poleIds),
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get feature collection map for signage project with all associated hiking routes and poles.
     * Per ogni hiking route associata, chiama il suo getFeatureCollectionMap() per includere
     * geometria, poles e geometria raw non controllata.
     * Mantiene tutte le proprietà necessarie per il frontend (signage, description, osmTags).
     * Le hiking routes sono sempre dell'app 1.
     * 
     * OTTIMIZZATO: 
     * - Carica properties e poles in batch per evitare query N+1
     * - Usa caching per evitare di rifare query pesanti ad ogni richiesta
     *
     * @return array GeoJSON feature collection
     */
    public function getFeatureCollectionMap(): array
    {

        // TEMPORANEO: Disabilita cache per debug
        // TODO: Riabilitare cache dopo aver risolto il problema
        $result = $this->buildFeatureCollectionMap();

        return $result;

        // Genera chiave di cache basata su ID progetto e hash delle hiking routes associate
        // Specifica esplicitamente la tabella per evitare ambiguità nella query
        // $hikingRouteIds = $this->hikingRoutes()->pluck('hiking_routes.id')->sort()->toArray();
        // $cacheKey = "signage_project_feature_collection_map_{$this->id}_" . md5(implode(',', $hikingRouteIds));

        // TTL cache: 1 ora (3600 secondi)
        // La cache viene invalidata automaticamente quando le hiking routes cambiano
        // return Cache::remember($cacheKey, 3600, function () {
        //     return $this->buildFeatureCollectionMap();
        // });
    }

    /**
     * Costruisce il feature collection map senza caching.
     * Metodo privato chiamato da getFeatureCollectionMap() dopo il controllo cache.
     * 
     * OTTIMIZZATO: Carica tutte le hiking routes usando query batch ottimizzate.
     *
     * @return array GeoJSON feature collection
     */
    private function buildFeatureCollectionMap(): array
    {
        try {
            $this->clearAdditionalFeaturesForMap();

            // STEP 1: Carica solo gli ID delle hiking routes (query molto veloce)
            $hikingRouteIds = $this->hikingRoutes()->pluck('hiking_routes.id')->toArray();

            if (empty($hikingRouteIds)) {
                return $this->getFeatureCollectionMapFromTrait();
            }

            // STEP 2: Carica solo i dati essenziali delle hiking routes in batch
            // Carica solo id, geometry, properties, osmfeatures_id - evita di caricare tutti i campi Eloquent
            $hikingRoutesData = DB::table('hiking_routes')
                ->whereIn('id', $hikingRouteIds)
                ->select('id', 'name', 'properties', 'osmfeatures_id', DB::raw('ST_AsGeoJSON(geometry) as geometry'), DB::raw('ST_AsGeoJSON(geometry_raw_data) as geometry_raw_data'))
                ->get();

            if ($hikingRoutesData->isEmpty()) {
                return $this->getFeatureCollectionMapFromTrait();
            }

            // Ottieni la lingua corrente dell'applicazione (it, en, ecc.)
            $currentLocale = app()->getLocale();

            // Decodifica le properties per ogni hiking route
            $hikingRoutesProcessed = [];
            foreach ($hikingRoutesData as $hrData) {
                $properties = is_string($hrData->properties) ? json_decode($hrData->properties, true) : $hrData->properties;
                $geometry = is_string($hrData->geometry) ? json_decode($hrData->geometry, true) : $hrData->geometry;
                $geometryRaw = $hrData->geometry_raw_data ? (is_string($hrData->geometry_raw_data) ? json_decode($hrData->geometry_raw_data, true) : $hrData->geometry_raw_data) : null;

                // Decodifica il name se è una stringa JSON (come viene salvato nel database)
                // Estrai il nome nella lingua corrente, con fallback a 'it' e poi al primo disponibile
                $translatedName = $this->extractTranslatedName($hrData->name, $currentLocale);

                $hikingRoutesProcessed[$hrData->id] = [
                    'id' => $hrData->id,
                    'name' => $translatedName,
                    'properties' => $properties ?? [],
                    'osmfeatures_id' => $hrData->osmfeatures_id,
                    'geometry' => $geometry,
                    'geometry_raw_data' => $geometryRaw,
                ];
            }

            // OTTIMIZZAZIONE 2: Carica tutti i poles in batch per tutte le hiking routes
            $polesByHikingRoute = $this->getAllPolesForHikingRoutes($hikingRouteIds);

            // OTTIMIZZAZIONE 3: Raccogli tutti gli ID dei poles e converti geometrie in batch
            $allPoleIds = [];
            foreach ($polesByHikingRoute as $poles) {
                $allPoleIds = array_merge($allPoleIds, $poles->pluck('id')->toArray());
            }
            $allPoleIds = array_unique($allPoleIds);

            // Converti tutte le geometrie dei poles in GeoJSON in una singola query batch
            // Questo elimina migliaia di query individuali (N+1 problem)
            $polesGeoJsonMap = $this->getPolesGeoJsonBatch($allPoleIds);

            // Array di colori per distinguere visivamente le diverse hiking routes
            // Usiamo colori distinti e ben visibili sulla mappa
            $routeColors = [
                'blue',
                'green',
                'purple',
                'orange',
                'cyan',
                'magenta',
                'lime',
                'pink',
                'yellow',
                'teal',
                'indigo',
                'coral',
                'gold',
                'navy',
                'maroon',
            ];

            // Mappa per deduplicare i pali (Point features) in base all'ID
            // Se un palo appare in più hiking routes, manteniamo quello con checkpoint (giallo)
            $polesMap = [];
            $otherFeatures = [];

            // Per ogni hiking route, genera le features manualmente usando i dati raw
            // - La geometria principale (linea blu) con signage
            // - I poles con buffer (punti rossi/arancioni) con tutte le proprietà
            // - La geometria raw non controllata (linea rossa) con signage
            foreach ($hikingRoutesProcessed as $index => $hrData) {
                $hrId = $hrData['id'];
                $hrProperties = $hrData['properties'] ?? [];

                // OTTIMIZZAZIONE: Genera le features manualmente usando i poles già caricati in batch
                $polesForThisRoute = $polesByHikingRoute[$hrId] ?? collect();

                // Crea la feature principale dalla geometria
                $mainFeature = null;
                if ($hrData['geometry']) {
                    $mainFeature = [
                        'type' => 'Feature',
                        'geometry' => $hrData['geometry'],
                        'properties' => [
                            'strokeColor' => 'blue',
                            'strokeWidth' => 4,
                            'id' => $hrId,
                            'osmfeatures_id' => $hrData['osmfeatures_id'],
                        ],
                    ];

                    if (isset($hrProperties['signage'])) {
                        $mainFeature['properties']['signage'] = $hrProperties['signage'];
                    }
                }

                // Genera le features per i poles usando i dati batch e geometrie pre-convertite
                $checkpointPoleIds = ($hrProperties['signage']['checkpoint'] ?? []);
                $exportIgnorePoleIds = ($hrProperties['signage']['export_ignore'] ?? []);
                $poleFeatures = $polesForThisRoute->map(function ($pole) use ($checkpointPoleIds, $exportIgnorePoleIds, $hrId, $polesGeoJsonMap) {
                    // OTTIMIZZAZIONE: Usa geometria pre-convertita invece di chiamare getFeatureMap() per ogni pole
                    // Questo elimina migliaia di query PostGIS individuali
                    $geojson = $polesGeoJsonMap[$pole->id] ?? null;

                    if (!$geojson) {
                        // Skip questo pole se la geometria non è disponibile (non dovrebbe succedere)
                        return null;
                    }

                    // Crea la feature GeoJSON usando la geometria pre-convertita
                    $poleFeature = [
                        'type' => 'Feature',
                        'geometry' => $geojson,
                        'properties' => [],
                    ];

                    $isCheckpoint = in_array($pole->id, $checkpointPoleIds);
                    $osmTags = null;
                    if ($pole->osmfeatures_data && isset($pole->osmfeatures_data['properties']['osm_tags'])) {
                        $osmTags = $pole->osmfeatures_data['properties']['osm_tags'];
                    }

                    $isExportIgnored = false;
                    foreach ($exportIgnorePoleIds as $ignoredId) {
                        if ((int) $ignoredId === (int) $pole->id || (string) $ignoredId === (string) $pole->id) {
                            $isExportIgnored = true;
                            break;
                        }
                    }

                    $isProposed = $osmTags && (
                        ($osmTags['lifecycle'] ?? null) === 'proposed'
                        || ($osmTags['proposed'] ?? null) === 'yes'
                    );

                    $properties = [
                        'id' => $pole->id,
                        'name' => $pole->name ?? '',
                        'description' => $pole->properties['description'] ?? '',
                        'tooltip' =>  $pole->name ?? $pole->ref ?? '',
                        'ref' => $pole->ref,
                        'clickAction' => 'popup',
                        'link' => url('/resources/poles/' . $pole->id),
                        'pointStrokeColor' => self::POINT_STROKE_COLOR,
                        'pointStrokeWidth' => self::POINT_STROKE_WIDTH,
                        'pointFillColor' => $isCheckpoint ? self::CHECKPOINT_FILL_COLOR : self::POINT_FILL_COLOR,
                        'pointRadius' => $isCheckpoint ? self::CHECKPOINT_RADIUS : self::POINT_RADIUS,
                        'signage' => $pole->properties['signage'] ?? [],
                        'osmTags' => $osmTags,
                        'exportIgnore' => $isExportIgnored,
                        'proposed' => $isProposed,
                    ];
                    $poleFeature['properties'] = $properties;

                    return $poleFeature;
                })->filter()->toArray(); // filter() rimuove eventuali null

                // Genera la feature per geometry_raw_data
                $uncheckedGeometryFeature = null;
                if ($hrData['geometry_raw_data']) {
                    $uncheckedGeometryFeature = [
                        'type' => 'Feature',
                        'geometry' => $hrData['geometry_raw_data'],
                        'properties' => [
                            'strokeColor' => 'red',
                            'strokeWidth' => 2,
                            'id' => $hrId,
                            'osmfeatures_id' => $hrData['osmfeatures_id'],
                        ],
                    ];

                    if (isset($hrProperties['signage'])) {
                        $uncheckedGeometryFeature['properties']['signage'] = $hrProperties['signage'];
                    }
                }

                // Combina tutte le features della hiking route
                $hikingRouteFeatures = ['features' => []];
                if (!empty($poleFeatures)) {
                    $hikingRouteFeatures['features'] = array_merge($hikingRouteFeatures['features'], $poleFeatures);
                }
                if ($uncheckedGeometryFeature) {
                    $hikingRouteFeatures['features'][] = $uncheckedGeometryFeature;
                }
                if ($mainFeature) {
                    $hikingRouteFeatures['features'][] = $mainFeature;
                }

                // Ottieni il colore assegnato a questa hiking route
                $colorIndex = $index % count($routeColors);
                $routeColor = $routeColors[$colorIndex];

                // Aggiungi TUTTE le features della hiking route per mantenere tutte le funzionalità interattive:
                // - Geometria principale (linea blu) - clickabile con signage
                // - Poles (punti) - clickabili per aprire popup segnaletica
                // - Geometria raw (linea rossa) - clickabile con signage
                if (isset($hikingRouteFeatures['features']) && !empty($hikingRouteFeatures['features'])) {
                    foreach ($hikingRouteFeatures['features'] as $feature) {
                        // Verifica che sia una feature GeoJSON valida
                        if (isset($feature['type']) && $feature['type'] === 'Feature') {
                            $geometryType = strtolower($feature['geometry']['type'] ?? '');
                            $isPoint = $geometryType === 'point';
                            $poleId = $feature['properties']['id'] ?? null;

                            // Se è un punto (palo), deduplica in base all'ID
                            if ($isPoint && $poleId !== null) {
                                // IMPORTANTE: Aggiungi l'ID dell'HikingRoute nelle properties del palo
                                // Questo permette al frontend di sapere quale HikingRoute contiene questo palo
                                // anche quando il palo non è ancora checkpoint
                                if (!isset($feature['properties']['hikingRouteId'])) {
                                    $feature['properties']['hikingRouteId'] = $hrId;
                                }

                                // Controlla se questo palo è checkpoint per questa hiking route
                                $isCheckpointForThisRoute = in_array($poleId, $checkpointPoleIds);

                                // Se è checkpoint, aggiungi il colore della hiking route all'array dei colori checkpoint
                                if (
                                    $isCheckpointForThisRoute &&
                                    ($feature['properties']['pointFillColor'] ?? '') === self::CHECKPOINT_FILL_COLOR
                                ) {
                                    // Inizializza l'array dei colori checkpoint se non esiste
                                    if (!isset($feature['properties']['checkpointRouteColors'])) {
                                        $feature['properties']['checkpointRouteColors'] = [];
                                    }
                                    // Aggiungi il colore se non è già presente
                                    if (!in_array($routeColor, $feature['properties']['checkpointRouteColors'])) {
                                        $feature['properties']['checkpointRouteColors'][] = $routeColor;
                                    }

                                    // Se c'è un solo colore, usa quello come strokeColor principale
                                    // Se ce ne sono più di uno, il frontend gestirà il rendering multicolore
                                    if (count($feature['properties']['checkpointRouteColors']) === 1) {
                                        $feature['properties']['pointStrokeColor'] = $routeColor;
                                    } else {
                                        // Per più colori, usa il primo come colore principale
                                        // Il frontend userà checkpointRouteColors per il rendering multicolore
                                        $feature['properties']['pointStrokeColor'] = $feature['properties']['checkpointRouteColors'][0];
                                    }
                                    // Aumenta anche lo spessore del bordo per renderlo più visibile
                                    $feature['properties']['pointStrokeWidth'] = 3;
                                }

                                $poleIdKey = (string) $poleId;

                                // Segna exportIgnore se questo palo è in export_ignore per questa route
                                $isExportIgnoredForThisRoute = in_array($poleId, $exportIgnorePoleIds = ($hrProperties['signage']['export_ignore'] ?? []))
                                    || in_array((string) $poleId, array_map('strval', $exportIgnorePoleIds));
                                if ($isExportIgnoredForThisRoute) {
                                    $feature['properties']['exportIgnore'] = true;
                                }

                                // Se il palo non esiste ancora, aggiungilo
                                if (!isset($polesMap[$poleIdKey])) {
                                    // Inizializza l'array delle HikingRoute associate a questo palo
                                    $feature['properties']['hikingRouteIds'] = [$hrId];

                                    // Se è checkpoint, assicurati che checkpointRouteColors sia impostato
                                    if (
                                        $isCheckpointForThisRoute &&
                                        ($feature['properties']['pointFillColor'] ?? '') === self::CHECKPOINT_FILL_COLOR
                                    ) {
                                        if (
                                            !isset($feature['properties']['checkpointRouteColors']) ||
                                            empty($feature['properties']['checkpointRouteColors'])
                                        ) {
                                            $feature['properties']['checkpointRouteColors'] = [$routeColor];
                                        }
                                    }

                                    $polesMap[$poleIdKey] = $feature;
                                } else {
                                    // Se il palo esiste già, aggiungi questa HikingRoute all'array
                                    if (!isset($polesMap[$poleIdKey]['properties']['hikingRouteIds'])) {
                                        // Se non esiste ancora, inizializza con l'HikingRoute esistente (se presente)
                                        $existingHrId = $polesMap[$poleIdKey]['properties']['hikingRouteId'] ?? null;
                                        $polesMap[$poleIdKey]['properties']['hikingRouteIds'] = $existingHrId ? [$existingHrId] : [];
                                    }
                                    // Aggiungi l'ID dell'HikingRoute se non è già presente
                                    if (!in_array($hrId, $polesMap[$poleIdKey]['properties']['hikingRouteIds'])) {
                                        $polesMap[$poleIdKey]['properties']['hikingRouteIds'][] = $hrId;
                                    }
                                    // Mantieni exportIgnore true se era già true o se questa route lo ha
                                    if (!empty($feature['properties']['exportIgnore'])) {
                                        $polesMap[$poleIdKey]['properties']['exportIgnore'] = true;
                                    }

                                    // Unisci le informazioni mantenendo la priorità al checkpoint
                                    $existingColor = $polesMap[$poleIdKey]['properties']['pointFillColor'] ?? '';
                                    $newColor = $feature['properties']['pointFillColor'] ?? '';

                                    // Se il nuovo palo è checkpoint (giallo) e quello esistente no (rosso),
                                    // sostituiscilo completamente (ha priorità perché è checkpoint)
                                    if ($newColor === self::CHECKPOINT_FILL_COLOR && $existingColor !== self::CHECKPOINT_FILL_COLOR) {
                                        // Mantieni l'array hikingRouteIds esistente e aggiungi questa HikingRoute
                                        $existingHrIds = $polesMap[$poleIdKey]['properties']['hikingRouteIds'] ?? [];
                                        $feature['properties']['hikingRouteIds'] = $existingHrIds;
                                        if (!in_array($hrId, $feature['properties']['hikingRouteIds'])) {
                                            $feature['properties']['hikingRouteIds'][] = $hrId;
                                        }
                                        // Assicurati che checkpointRouteColors sia presente se il nuovo è checkpoint
                                        if (!isset($feature['properties']['checkpointRouteColors']) || empty($feature['properties']['checkpointRouteColors'])) {
                                            $feature['properties']['checkpointRouteColors'] = [$routeColor];
                                        }
                                        $polesMap[$poleIdKey] = $feature;
                                    } elseif ($existingColor === self::CHECKPOINT_FILL_COLOR && $newColor !== self::CHECKPOINT_FILL_COLOR) {
                                        // Se quello esistente è checkpoint e il nuovo no, mantieni quello esistente
                                        // (non fare nulla, già corretto)
                                    } elseif ($newColor === self::CHECKPOINT_FILL_COLOR && $existingColor === self::CHECKPOINT_FILL_COLOR) {
                                        // Se entrambi sono checkpoint, unisci i colori checkpoint
                                        $existingCheckpointColors = $polesMap[$poleIdKey]['properties']['checkpointRouteColors'] ?? [];
                                        $newCheckpointColors = $feature['properties']['checkpointRouteColors'] ?? [];

                                        // Se existingCheckpointColors è vuoto ma il palo esistente ha un pointStrokeColor,
                                        // aggiungilo come primo colore
                                        if (empty($existingCheckpointColors) && !empty($polesMap[$poleIdKey]['properties']['pointStrokeColor'])) {
                                            $existingStrokeColor = $polesMap[$poleIdKey]['properties']['pointStrokeColor'];
                                            // Solo se non è il colore di default bianco
                                            if ($existingStrokeColor !== self::POINT_STROKE_COLOR) {
                                                $existingCheckpointColors = [$existingStrokeColor];
                                            }
                                        }

                                        // Se newCheckpointColors è vuoto ma la feature ha un pointStrokeColor,
                                        // aggiungilo come nuovo colore
                                        if (empty($newCheckpointColors) && !empty($feature['properties']['pointStrokeColor'])) {
                                            $newStrokeColor = $feature['properties']['pointStrokeColor'];
                                            // Solo se non è il colore di default bianco
                                            if ($newStrokeColor !== self::POINT_STROKE_COLOR) {
                                                $newCheckpointColors = [$newStrokeColor];
                                            }
                                        }

                                        // Se ancora vuoto, usa il routeColor corrente
                                        if (empty($newCheckpointColors)) {
                                            $newCheckpointColors = [$routeColor];
                                        }

                                        // Unisci i colori checkpoint, rimuovendo duplicati
                                        $mergedCheckpointColors = array_unique(array_merge($existingCheckpointColors, $newCheckpointColors));

                                        // Aggiorna l'array dei colori checkpoint
                                        $polesMap[$poleIdKey]['properties']['checkpointRouteColors'] = array_values($mergedCheckpointColors);

                                        // Se c'è un solo colore, usa quello come strokeColor principale
                                        if (count($mergedCheckpointColors) === 1) {
                                            $polesMap[$poleIdKey]['properties']['pointStrokeColor'] = $mergedCheckpointColors[0];
                                        } else {
                                            // Per più colori, usa il primo come colore principale
                                            // Il frontend userà checkpointRouteColors per il rendering multicolore
                                            $polesMap[$poleIdKey]['properties']['pointStrokeColor'] = $mergedCheckpointColors[0];
                                        }

                                        // Assicurati che lo spessore del bordo sia aumentato
                                        $polesMap[$poleIdKey]['properties']['pointStrokeWidth'] = 3;

                                        // Unisci comunque i dati di signage per non perdere informazioni
                                        $existingSignage = $polesMap[$poleIdKey]['properties']['signage'] ?? [];
                                        $newSignage = $feature['properties']['signage'] ?? [];

                                        if (!empty($newSignage) && is_array($newSignage)) {
                                            // Merge intelligente: unisci le chiavi delle hiking routes senza sovrascrivere
                                            // Se una chiave esiste già in existingSignage, mantienila (è già completa)
                                            // Aggiungi solo le nuove chiavi da newSignage
                                            $mergedSignage = $existingSignage;

                                            foreach ($newSignage as $key => $value) {
                                                // Se è arrow_order, unisci gli array rimuovendo duplicati
                                                if ($key === 'arrow_order') {
                                                    if (isset($existingSignage['arrow_order']) && is_array($existingSignage['arrow_order'])) {
                                                        $mergedSignage['arrow_order'] = array_values(array_unique(array_merge(
                                                            $existingSignage['arrow_order'],
                                                            is_array($value) ? $value : []
                                                        )));
                                                    } elseif (is_array($value)) {
                                                        $mergedSignage['arrow_order'] = array_values(array_unique($value));
                                                    }
                                                } else {
                                                    // Per le altre chiavi (ID hiking routes), aggiungi solo se non esistono già
                                                    // Questo evita di sovrascrivere dati già presenti
                                                    if (!isset($mergedSignage[$key])) {
                                                        $mergedSignage[$key] = $value;
                                                    }
                                                }
                                            }

                                            $polesMap[$poleIdKey]['properties']['signage'] = $mergedSignage;
                                        }
                                    } else {
                                        // Se entrambi hanno lo stesso stato (entrambi non checkpoint),
                                        // unisci i dati di signage per non perdere informazioni
                                        $existingSignage = $polesMap[$poleIdKey]['properties']['signage'] ?? [];
                                        $newSignage = $feature['properties']['signage'] ?? [];

                                        // Unisci i dati di signage (possono contenere informazioni da diverse hiking routes)
                                        if (!empty($newSignage) && is_array($newSignage)) {
                                            // Merge intelligente: unisci le chiavi delle hiking routes senza sovrascrivere
                                            // Se una chiave esiste già in existingSignage, mantienila (è già completa)
                                            // Aggiungi solo le nuove chiavi da newSignage
                                            $mergedSignage = $existingSignage;

                                            foreach ($newSignage as $key => $value) {
                                                // Se è arrow_order, unisci gli array rimuovendo duplicati
                                                if ($key === 'arrow_order') {
                                                    if (isset($existingSignage['arrow_order']) && is_array($existingSignage['arrow_order'])) {
                                                        $mergedSignage['arrow_order'] = array_values(array_unique(array_merge(
                                                            $existingSignage['arrow_order'],
                                                            is_array($value) ? $value : []
                                                        )));
                                                    } elseif (is_array($value)) {
                                                        $mergedSignage['arrow_order'] = array_values(array_unique($value));
                                                    }
                                                } else {
                                                    // Per le altre chiavi (ID hiking routes), aggiungi solo se non esistono già
                                                    // Questo evita di sovrascrivere dati già presenti
                                                    if (!isset($mergedSignage[$key])) {
                                                        $mergedSignage[$key] = $value;
                                                    }
                                                }
                                            }

                                            $polesMap[$poleIdKey]['properties']['signage'] = $mergedSignage;
                                        }
                                    }
                                }
                            } else {
                                // Per le altre features (LineString, MultiLineString), aggiungile sempre
                                // perché rappresentano le geometrie delle hiking routes
                                // Assegna un colore diverso a ciascuna hiking route per distinguerle visivamente
                                $geometryType = strtolower($feature['geometry']['type'] ?? '');
                                if (in_array($geometryType, ['linestring', 'multilinestring'])) {
                                    // Assegna un colore basato sull'indice della hiking route
                                    $colorIndex = $index % count($routeColors);
                                    $routeColor = $routeColors[$colorIndex];

                                    // Modifica il colore della linea mantenendo le altre proprietà
                                    if (isset($feature['properties']['strokeColor'])) {
                                        $feature['properties']['strokeColor'] = $routeColor;
                                    }

                                    // Aggiungi anche l'ID della hiking route per riferimento
                                    if (!isset($feature['properties']['hikingRouteId'])) {
                                        $feature['properties']['hikingRouteId'] = $hrId;
                                    }

                                    // Aggiungi clickAction e link per aprire l'HikingRoute in un nuovo tab
                                    $feature['properties']['clickAction'] = 'link';
                                    $feature['properties']['link'] = url('/resources/hiking-routes/' . $hrId);

                                    // Aggiungi tooltip con il nome dell'HikingRoute se disponibile
                                    if (!isset($feature['properties']['tooltip']) && $hrData['name']) {
                                        $feature['properties']['tooltip'] = is_array($hrData['name'])
                                            ? ($hrData['name']['it'] ?? reset($hrData['name']))
                                            : $hrData['name'];
                                    }
                                }
                                $otherFeatures[] = $feature;
                            }
                        }
                    }
                }
            }

            // Aggiungi prima i pali deduplicati, poi le altre features
            $this->additionalFeaturesForMap = array_merge(array_values($polesMap), $otherFeatures);

            // Usa getFeatureCollectionMapFromTrait per includere la geometria principale del SignageProject
            // Questo metodo gestisce automaticamente la geometria principale e unisce le features aggiuntive
            $result = $this->getFeatureCollectionMapFromTrait();

            // Verifica che il risultato sia valido
            if (empty($result['features'])) {
                Log::warning("SignageProject::buildFeatureCollectionMap - Empty result", [
                    'signage_project_id' => $this->id,
                    'has_geometry' => !empty($this->geometry),
                    'additional_features_count' => count($this->additionalFeaturesForMap),
                    'result' => $result,
                ]);

                // Fallback: restituisci almeno le features aggiuntive se ci sono
                if (!empty($this->additionalFeaturesForMap)) {
                    return [
                        'type' => 'FeatureCollection',
                        'features' => $this->additionalFeaturesForMap,
                    ];
                }
            }

            return $result;
        } catch (\Exception $e) {
            // In caso di errore, logga e restituisci almeno la geometria principale
            Log::error('SignageProject::buildFeatureCollectionMap error', [
                'signage_project_id' => $this->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Restituisci almeno la geometria principale per non bloccare completamente la pagina
            return $this->getFeatureCollectionMapFromTrait();
        }
    }

    /**
     * Get the name as a string with fallback logic.
     * Priority: Current locale -> Italian -> English -> First available language
     */
    public function getStringName(): string
    {
        $value = $this->getRawOriginal('name');

        // Se il valore è una stringa JSON, decodificalo
        if (is_string($value) && $this->isJsonString($value)) {
            $value = json_decode($value, true);
        }

        // Se il valore è già una stringa (non tradotto), restituiscilo
        if (is_string($value) && ! $this->isJsonString($value)) {
            return $value;
        }

        // Se è un array/oggetto con chiavi di lingua
        if (is_array($value)) {
            $currentLocale = app()->getLocale();

            // Prima priorità: lingua corrente
            if (isset($value[$currentLocale]) && ! empty($value[$currentLocale])) {
                return $value[$currentLocale];
            }

            // Seconda priorità: italiano
            if (isset($value['it']) && ! empty($value['it'])) {
                return $value['it'];
            }

            // Terza priorità: inglese
            if (isset($value['en']) && ! empty($value['en'])) {
                return $value['en'];
            }

            // Quarta priorità: prima lingua disponibile (non vuota)
            foreach ($value as $translation) {
                if (! empty($translation)) {
                    return $translation;
                }
            }
        }

        // Fallback finale
        return '';
    }

    /**
     * Controlla se una stringa è un JSON valido
     *
     * @param  string  $string  La stringa da controllare
     * @return bool True se è un JSON valido
     */
    private function isJsonString(string $string): bool
    {
        if (empty($string)) {
            return false;
        }

        // Deve iniziare con { o [
        $trimmed = trim($string);
        if (! str_starts_with($trimmed, '{') && ! str_starts_with($trimmed, '[')) {
            return false;
        }

        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Get optimized query for hiking routes in this signage project.
     * Le hiking routes sono sempre dell'app 1.
     */
    private function getOptimizedHikingRoutes(): string
    {
        $sql = "
        SELECT 
            hr.id,
            hr.name,
            hr.properties,
            ST_AsGeoJSON(hr.geometry) as geometry
        FROM signage_projectables sp
        INNER JOIN hiking_routes hr ON hr.id = sp.signage_projectable_id
        WHERE sp.signage_project_id = ?
            AND sp.signage_projectable_type = ?
            AND hr.geometry IS NOT NULL
            AND hr.app_id = 1
        ORDER BY hr.id
    ";

        return $sql;
    }

    /**
     * Invalida la cache del feature collection map per questo progetto.
     * Viene chiamato automaticamente quando il progetto viene aggiornato o eliminato.
     * Dovrebbe essere chiamato anche quando le hiking routes vengono aggiunte/rimosse.
     */
    public function clearFeatureCollectionMapCache(): void
    {
        // Invalida tutte le possibili chiavi di cache per questo progetto
        // Usa un pattern per trovare tutte le varianti (con diversi hash di hiking routes)
        $pattern = "signage_project_feature_collection_map_{$this->id}_*";

        // Se usi Redis o un altro driver che supporta pattern matching
        if (config('cache.default') === 'redis') {
            $keys = Cache::getRedis()->keys("{$pattern}");
            if (!empty($keys)) {
                Cache::deleteMultiple($keys);
            }
        } else {
            // Per altri driver, invalida manualmente quando possibile
            // La cache verrà comunque invalidata automaticamente quando cambiano le hiking routes
            // perché la chiave include l'hash delle hiking routes
        }
    }

    /**
     * Estrae il nome tradotto dal campo name (che può essere una stringa JSON o una stringa semplice)
     *
     * @param  string|null  $name  Il valore del campo name (può essere JSON o stringa)
     * @param  string  $locale  La lingua corrente (es. 'it', 'en')
     * @return string Il nome nella lingua corrente, o fallback a 'it' o al primo disponibile
     */
    protected function extractTranslatedName(?string $name, string $locale = 'it'): string
    {
        if (empty($name)) {
            return '';
        }

        // Se è una stringa JSON, decodificala
        if (is_string($name)) {
            $decodedName = json_decode($name, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedName)) {
                // Priorità: 1) Lingua corrente, 2) Italiano, 3) Prima disponibile, 4) Stringa originale
                return $decodedName[$locale]
                    ?? $decodedName['it']
                    ?? (is_array($decodedName) && ! empty($decodedName) ? reset($decodedName) : $name)
                    ?? $name;
            }
        }

        // Se non è JSON o è già una stringa semplice, restituiscila così com'è
        return $name;
    }
}
