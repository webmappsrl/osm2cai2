<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Auth;
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
     * Get all poles for multiple hiking routes in a single batch query.
     * Ottimizzazione per evitare query N+1 quando ci sono molte hiking routes.
     * 
     * NOTA: Per ora facciamo una query per hiking route, ma questo è comunque molto più efficiente
     * rispetto al metodo originale perché:
     * 1. Non chiamiamo fresh() per ogni route
     * 2. Non facciamo query individuali per properties
     * 3. Non chiamiamo getPolesWithBuffer() che fa query aggiuntive
     *
     * @param array $hikingRouteIds Array di ID delle hiking routes
     * @param float $bufferDistance Distanza del buffer in metri (default: 10m)
     * @return array Mappa hiking_route_id => Collection<Poles>
     */
    private function getAllPolesForHikingRoutes(array $hikingRouteIds, float $bufferDistance = 10): array
    {
        if (empty($hikingRouteIds)) {
            return [];
        }

        // Inizializza la mappa risultato
        $polesByHikingRoute = [];
        foreach ($hikingRouteIds as $hrId) {
            $polesByHikingRoute[$hrId] = collect();
        }

        // Carica tutte le geometrie delle hiking routes in una singola query
        $geometries = DB::table('hiking_routes')
            ->whereIn('id', $hikingRouteIds)
            ->whereNotNull('geometry')
            ->select('id', DB::raw('ST_AsGeoJSON(geometry) as geometry'))
            ->get()
            ->keyBy('id');

        if ($geometries->isEmpty()) {
            return $polesByHikingRoute;
        }

        // Trova tutti i poles per ogni hiking route
        // Anche se facciamo n query (una per hiking route), questo è molto più efficiente
        // rispetto al metodo originale perché evitiamo fresh() e query per properties
        foreach ($geometries as $hrId => $geom) {
            try {
                $poles = Poles::select('poles.*')
                    ->whereRaw(
                        'ST_DWithin(poles.geometry, ST_GeomFromGeoJSON(?)::geography, ?)',
                        [$geom->geometry, $bufferDistance]
                    )
                    ->get();

                $polesByHikingRoute[$hrId] = $poles;
            } catch (\Exception $e) {
                // In caso di errore per una specifica hiking route, continua con le altre
                Log::warning("Error loading poles for hiking route {$hrId}", [
                    'error' => $e->getMessage(),
                ]);
                $polesByHikingRoute[$hrId] = collect();
            }
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
     * OTTIMIZZATO: Carica properties e poles in batch per evitare query N+1.
     *
     * @return array GeoJSON feature collection
     */
    public function getFeatureCollectionMap(): array
    {
        try {
            $this->clearAdditionalFeaturesForMap();

            // Recupera tutte le hiking routes associate come modelli (sempre dell'app 1)
            $hikingRouteModels = $this->hikingRoutes()->get();

            if ($hikingRouteModels->isEmpty()) {
                return $this->getFeatureCollectionMapFromTrait();
            }

            // OTTIMIZZAZIONE 1: Carica tutte le properties in batch invece di query individuali
            $hikingRouteIds = $hikingRouteModels->pluck('id')->toArray();
            $propertiesBatch = DB::table('hiking_routes')
                ->whereIn('id', $hikingRouteIds)
                ->select('id', 'properties')
                ->get()
                ->keyBy('id');

            // Decodifica e assegna le properties a ciascun modello
            foreach ($hikingRouteModels as $hikingRoute) {
                $properties = $propertiesBatch->get($hikingRoute->id);
                if ($properties) {
                    $props = $properties->properties;
                    if (is_string($props)) {
                        $hikingRoute->properties = json_decode($props, true) ?? [];
                    } else {
                        $hikingRoute->properties = $props ?? [];
                    }
                } else {
                    $hikingRoute->properties = [];
                }
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

            // Per ogni hiking route, chiama il suo getFeatureCollectionMap() che include:
            // - La geometria principale (linea blu) con signage
            // - I poles con buffer (punti rossi/arancioni) con tutte le proprietà
            // - La geometria raw non controllata (linea rossa) con signage
            foreach ($hikingRouteModels as $index => $hikingRoute) {
                // Assicurati che le properties siano array (già fatto sopra, ma doppio controllo)
                if (!is_array($hikingRoute->properties)) {
                    $hikingRoute->properties = [];
                }

                // OTTIMIZZAZIONE 3: Genera le features manualmente usando i poles già caricati in batch
                // invece di chiamare getFeatureCollectionMap() che chiamerebbe getPolesWithBuffer()
                $polesForThisRoute = $polesByHikingRoute[$hikingRoute->id] ?? collect();

                // Ottieni la geometria principale della hiking route
                $mainGeometryGeoJson = $hikingRoute->getFeatureCollectionMapFromTrait();
                $mainFeature = $mainGeometryGeoJson['features'][0] ?? null;

                if ($mainFeature) {
                    $mainFeature['properties'] = [
                        'strokeColor' => 'blue',
                        'strokeWidth' => 4,
                        'id' => $hikingRoute->id,
                        'osmfeatures_id' => $hikingRoute->osmfeatures_id,
                    ];

                    $hikingRouteProperties = $hikingRoute->properties ?? [];
                    if (isset($hikingRouteProperties['signage'])) {
                        $mainFeature['properties']['signage'] = $hikingRouteProperties['signage'];
                    }
                }

                // Genera le features per i poles usando i dati batch e geometrie pre-convertite
                $checkpointPoleIds = ($hikingRoute->properties['signage']['checkpoint'] ?? []);
                $poleFeatures = $polesForThisRoute->map(function ($pole) use ($checkpointPoleIds, $hikingRoute, $polesGeoJsonMap) {
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

                    $properties = [
                        'id' => $pole->id,
                        'name' => $pole->name ?? '',
                        'description' => $pole->properties['description'] ?? '',
                        'tooltip' => $pole->ref,
                        'ref' => $pole->ref,
                        'clickAction' => 'popup',
                        'link' => url('/resources/poles/' . $pole->id),
                        'pointStrokeColor' => self::POINT_STROKE_COLOR,
                        'pointStrokeWidth' => self::POINT_STROKE_WIDTH,
                        'pointFillColor' => $isCheckpoint ? self::CHECKPOINT_FILL_COLOR : self::POINT_FILL_COLOR,
                        'pointRadius' => $isCheckpoint ? self::CHECKPOINT_RADIUS : self::POINT_RADIUS,
                        'signage' => $pole->properties['signage'] ?? [],
                        'osmTags' => $osmTags,
                    ];
                    $poleFeature['properties'] = $properties;

                    return $poleFeature;
                })->filter()->toArray(); // filter() rimuove eventuali null

                // Genera la feature per geometry_raw_data
                $uncheckedGeometryFeature = null;
                if ($hikingRoute->geometry_raw_data) {
                    $uncheckedGeometryFeature = $hikingRoute->getFeatureMap($hikingRoute->geometry_raw_data);
                    $properties = [
                        'strokeColor' => 'red',
                        'strokeWidth' => 2,
                        'id' => $hikingRoute->id,
                        'osmfeatures_id' => $hikingRoute->osmfeatures_id,
                    ];
                    $hikingRouteProperties = $hikingRoute->properties ?? [];
                    if (isset($hikingRouteProperties['signage'])) {
                        $properties['signage'] = $hikingRouteProperties['signage'];
                    }
                    $uncheckedGeometryFeature['properties'] = $properties;
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
                                    $feature['properties']['hikingRouteId'] = $hikingRoute->id;
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

                                // Se il palo non esiste ancora, aggiungilo
                                if (!isset($polesMap[$poleIdKey])) {
                                    // Inizializza l'array delle HikingRoute associate a questo palo
                                    $feature['properties']['hikingRouteIds'] = [$hikingRoute->id];

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
                                    if (!in_array($hikingRoute->id, $polesMap[$poleIdKey]['properties']['hikingRouteIds'])) {
                                        $polesMap[$poleIdKey]['properties']['hikingRouteIds'][] = $hikingRoute->id;
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
                                        if (!in_array($hikingRoute->id, $feature['properties']['hikingRouteIds'])) {
                                            $feature['properties']['hikingRouteIds'][] = $hikingRoute->id;
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
                                            $mergedSignage = array_merge($existingSignage, $newSignage);

                                            if (isset($existingSignage['arrow_order']) && isset($newSignage['arrow_order'])) {
                                                $mergedSignage['arrow_order'] = array_unique(array_merge(
                                                    $existingSignage['arrow_order'] ?? [],
                                                    $newSignage['arrow_order'] ?? []
                                                ));
                                            } elseif (isset($newSignage['arrow_order'])) {
                                                $mergedSignage['arrow_order'] = $newSignage['arrow_order'];
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
                                            $mergedSignage = array_merge($existingSignage, $newSignage);

                                            // Unisci anche arrow_order se presente
                                            if (isset($existingSignage['arrow_order']) && isset($newSignage['arrow_order'])) {
                                                $mergedSignage['arrow_order'] = array_unique(array_merge(
                                                    $existingSignage['arrow_order'] ?? [],
                                                    $newSignage['arrow_order'] ?? []
                                                ));
                                            } elseif (isset($newSignage['arrow_order'])) {
                                                $mergedSignage['arrow_order'] = $newSignage['arrow_order'];
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
                                        $feature['properties']['hikingRouteId'] = $hikingRoute->id;
                                    }

                                    // Aggiungi clickAction e link per aprire l'HikingRoute in un nuovo tab
                                    $feature['properties']['clickAction'] = 'link';
                                    $feature['properties']['link'] = url('/resources/hiking-routes/' . $hikingRoute->id);

                                    // Aggiungi tooltip con il nome dell'HikingRoute se disponibile
                                    if (!isset($feature['properties']['tooltip']) && $hikingRoute->name) {
                                        $feature['properties']['tooltip'] = is_array($hikingRoute->name)
                                            ? ($hikingRoute->name['it'] ?? reset($hikingRoute->name))
                                            : $hikingRoute->name;
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
            return $this->getFeatureCollectionMapFromTrait();
        } catch (\Exception $e) {
            // In caso di errore, logga e restituisci almeno la geometria principale
            Log::error('SignageProject::getFeatureCollectionMap error', [
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
}
