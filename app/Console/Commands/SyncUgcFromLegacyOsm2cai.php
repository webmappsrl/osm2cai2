<?php

namespace App\Console\Commands;

use App\Models\UgcMedia;
use App\Models\UgcPoi;
use App\Models\UgcTrack;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SyncUgcFromLegacyOsm2cai extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:sync-ugc {--model= : The model to sync (pois/tracks/media)} { --id= : The id of the UGC to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync UGC from legacy OSM2CAI';

    /**
     * Available UGC types to import
     */
    private const UGC_TYPES = [
        'pois' => 'importUgcPois',
        'tracks' => 'importUgcTracks',
        'media' => 'importUgcMedia',
    ];

    /**
     * Default geometry for items without valid coordinates
     */
    private const DEFAULT_FALLBACK_POINT = '11.7 41.3'; // Point near Sardinia

    /**
     * Database connection to legacy database
     */
    private $legacyDb;

    /**
     * Cache for users to avoid multiple DB queries
     */
    private $userCache = [];

    /**
     * Logs for import operations
     */
    private $logs = [
        'invalidGeometries' => [],
        'ugcMedia' => [],
        'skippedNullUrlMedia' => [],
    ];

    public function __construct()
    {
        parent::__construct();
        $this->legacyDb = DB::connection('legacyosm2cai');
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->resetLogs();
        $model = $this->option('model');

        if (! $model) {
            $this->importAll();
        } elseif (! in_array($model, array_keys(self::UGC_TYPES))) {
            $this->error('Invalid model: '.$model);

            return;
        } else {
            $importMethod = self::UGC_TYPES[$model];
            $this->$importMethod();
        }

        $this->saveLogs();
        Log::info('SyncUgcFromLegacyOsm2caiCommand finished');
    }

    /**
     * Import all UGC types
     */
    private function importAll(): void
    {
        foreach (self::UGC_TYPES as $importMethod) {
            $this->$importMethod();
        }
    }

    /**
     * Save logs to storage
     */
    private function saveLogs(): void
    {
        if (! empty($this->logs['invalidGeometries'])) {
            $content = implode("\n", $this->logs['invalidGeometries']);
            Storage::put('invalid_geometries_ugc.txt', $content);
            $this->info('Invalid geometries have been logged to invalid_geometries_ugc.txt');
        }

        if (! empty($this->logs['ugcMedia']) || ! empty($this->logs['skippedNullUrlMedia'])) {
            $content = implode("\n", $this->logs['ugcMedia']);

            if (! empty($this->logs['skippedNullUrlMedia'])) {
                $content .= "\n\n----- MEDIA SKIPPED DUE TO NULL RELATIVE_URL -----\n";
                $content .= implode("\n", $this->logs['skippedNullUrlMedia']);
            }

            Storage::put('ugc_media_import_log.txt', $content);
            $this->info('UGC Media import logs have been saved to ugc_media_import_log.txt');
        }
    }

    /**
     * Reset logs before each command execution
     */
    private function resetLogs(): void
    {
        $this->logs = [
            'invalidGeometries' => [],
            'ugcMedia' => [],
            'skippedNullUrlMedia' => [],
        ];
    }

    /**
     * Find or create user by ID
     *
     * @param  int|null  $userId  Legacy user ID
     */
    private function ensureUserExists(?int $userId): ?User
    {
        if (! $userId) {
            return null;
        }

        // Check cache first
        if (isset($this->userCache[$userId])) {
            return $this->userCache[$userId];
        }

        $legacyUser = $this->legacyDb->table('users')
            ->where('id', $userId)
            ->first();

        if (! $legacyUser) {
            $this->error('User not found: '.($userId ?? 'empty user_id'));
            $this->userCache[$userId] = null;

            return null;
        }

        $user = User::where('email', $legacyUser->email)->first();
        if (! $user) {
            $user = $this->createUser($legacyUser);
        }

        // Cache the result
        $this->userCache[$userId] = $user;

        return $user;
    }

    /**
     * Create new user from legacy data
     *
     * @param  object  $legacyUser  User data from legacy database
     */
    private function createUser(object $legacyUser): User
    {
        $user = new User;
        $user->id = $legacyUser->id;
        $user->name = $legacyUser->name;
        $user->email = $legacyUser->email;
        $user->phone = $legacyUser->phone;
        $user->password = $legacyUser->password;
        $user->remember_token = $legacyUser->remember_token;
        $user->created_at = $legacyUser->created_at;
        $user->updated_at = now();

        try {
            $user->save();
        } catch (\Exception $e) {
            $this->error('Error importing user: '.$e->getMessage());
        }

        return $user;
    }

    /**
     * Import UGC media from legacy database
     */
    private function importUgcMedia(): void
    {
        $query = $this->legacyDb->table('ugc_media');
        $query = $this->option('id') ? $query->where('id', $this->option('id')) : $query;
        $legacyMedia = $query->get();

        $this->info('Starting UGC Media import. Total to process: '.$legacyMedia->count());
        $skippedCount = 0;

        foreach ($legacyMedia as $media) {
            if ($media->relative_url === null) {
                $this->logs['skippedNullUrlMedia'][] = "UGC Media ID: {$media->id}";
                $skippedCount++;

                continue;
            }

            try {
                // Get related media references and user
                $mediaUser = $this->ensureUserExists($media->user_id);
                [$ugcPoiId, $ugcTrackId, $relatedUgcPoi, $relatedUgcTrack] = $this->getMediaRelationships($media->id);

                $this->info('Importing UGC media: '.$media->id);
                $imageUrl = $this->getMediaImageUrl($media->relative_url);

                // Store image if needed
                $this->storeMediaImage($imageUrl, $media->relative_url, $media->id);

                // Process geometry
                $geometry = $this->processMediaGeometry(
                    $media->geometry,
                    $imageUrl,
                    $media->raw_data,
                    $relatedUgcPoi,
                    $relatedUgcTrack,
                    $media->id
                );

                // Check for duplicate geohub_id
                if (! $this->isDuplicateMedia($media)) {
                    // Create or update the media record
                    UgcMedia::updateOrCreate(
                        ['id' => $media->id],
                        [
                            'geohub_id' => $media->geohub_id,
                            'created_at' => $media->created_at,
                            'updated_at' => now(),
                            'name' => $media->name,
                            'description' => $media->description,
                            'geometry' => $geometry,
                            'user_id' => $mediaUser->id ?? null,
                            'ugc_poi_id' => $ugcPoiId,
                            'ugc_track_id' => $ugcTrackId,
                            'raw_data' => $this->normalizeRawData($media->raw_data),
                            'taxonomy_wheres' => $media->taxonomy_wheres,
                            'relative_url' => $media->relative_url,
                            'app_id' => $media->app_id,
                        ]
                    );
                }
            } catch (\Exception $e) {
                $this->logs['ugcMedia'][] = "[UGC Media ID: {$media->id}] IMPORT FAILED: ".$e->getMessage();
                $this->error("Error importing UGC Media ID {$media->id}: ".$e->getMessage());
            }
        }

        if ($skippedCount > 0) {
            $this->info("UGC Media import completed. Skipped {$skippedCount} media with NULL relative_url");
        } else {
            $this->info('UGC Media import completed');
        }
    }

    /**
     * Get UGC media relationships (POI and Track)
     *
     * @param  int  $mediaId  The media ID to find relationships for
     * @return array [$ugcPoiId, $ugcTrackId, $relatedUgcPoi, $relatedUgcTrack]
     */
    private function getMediaRelationships(int $mediaId): array
    {
        $ugcPoiId = null;
        $ugcTrackId = null;
        $relatedUgcPoi = null;
        $relatedUgcTrack = null;

        // Check for POI relationship
        $poiRelation = $this->legacyDb
            ->table('ugc_media_ugc_poi')
            ->where('ugc_media_id', $mediaId)
            ->first();

        if ($poiRelation) {
            $relatedUgcPoi = UgcPoi::find($poiRelation->ugc_poi_id);
            if ($relatedUgcPoi) {
                $ugcPoiId = $relatedUgcPoi->id;
            } else {
                $this->logs['ugcMedia'][] = "[UGC Media ID: {$mediaId}] Referenced UGC POI ID {$poiRelation->ugc_poi_id} not found";
            }
        }

        // Check for Track relationship
        $trackRelation = $this->legacyDb
            ->table('ugc_media_ugc_track')
            ->where('ugc_media_id', $mediaId)
            ->first();

        if ($trackRelation) {
            $relatedUgcTrack = UgcTrack::find($trackRelation->ugc_track_id);
            if ($relatedUgcTrack) {
                $ugcTrackId = $relatedUgcTrack->id;
            } else {
                $this->logs['ugcMedia'][] = "[UGC Media ID: {$mediaId}] Referenced UGC Track ID {$trackRelation->ugc_track_id} not found";
            }
        }

        return [$ugcPoiId, $ugcTrackId, $relatedUgcPoi, $relatedUgcTrack];
    }

    /**
     * Get the full image URL from relative URL
     *
     * @return string Full image URL
     */
    private function getMediaImageUrl(string $relativeUrl): string
    {
        return strpos($relativeUrl, 'http') === 0
            ? $relativeUrl
            : "https://osm2cai.cai.it/storage/{$relativeUrl}";
    }

    /**
     * Store media image to local storage if needed
     *
     * @param  string  $imageUrl  Full image URL
     * @param  string  $relativeUrl  Original relative URL
     * @param  int  $mediaId  Media ID for logging
     */
    private function storeMediaImage(string $imageUrl, string $relativeUrl, int $mediaId): void
    {
        if (str_starts_with($imageUrl, 'https://geohub.webmapp.it/')) {
            return; // Don't store geohub images locally
        }

        try {
            $imageContent = Http::get($imageUrl)->body();
            $imagePath = 'ugc-media/'.basename($relativeUrl);

            // Check if the image already exists
            if (! Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->put($imagePath, $imageContent);
            }
        } catch (\Exception $e) {
            $this->logs['ugcMedia'][] = "[UGC Media ID: {$mediaId}] Error downloading image: ".$e->getMessage();
        }
    }

    /**
     * Process media geometry using multiple fallback strategies
     *
     * @param  mixed  $geometry  Original geometry
     * @param  string  $imageUrl  Image URL for EXIF extraction
     * @param  mixed  $rawData  Raw data that might contain coordinates
     * @param  UgcPoi|null  $relatedUgcPoi  Related POI that might have coordinates
     * @param  UgcTrack|null  $relatedUgcTrack  Related track that might have coordinates
     * @param  int  $mediaId  Media ID for logging
     * @return mixed Processed geometry
     */
    private function processMediaGeometry($geometry, string $imageUrl, $rawData, ?UgcPoi $relatedUgcPoi, ?UgcTrack $relatedUgcTrack, int $mediaId)
    {
        $geometryErrorMessage = null;
        $isInvalidGeometry = ! $geometry || $this->isPointZero($geometry);

        if (! $isInvalidGeometry) {
            $geometry = $this->normalizeNonPointGeometry($geometry, $mediaId);
            $isInvalidGeometry = $geometry === null;
        }

        // Try multiple fallback strategies if geometry is invalid
        if ($isInvalidGeometry) {
            // Strategy 1: Get coordinates from EXIF data
            $exifCoords = $this->getExifCoordinates($imageUrl);
            if ($exifCoords) {
                return DB::raw("ST_GeomFromText('POINT({$exifCoords['lon']} {$exifCoords['lat']})', 4326)");
            }

            // Strategy 2: Get coordinates from raw_data
            $rawDataCoords = $this->getCoordinatesFromRawData($rawData);
            if ($rawDataCoords) {
                return DB::raw("ST_GeomFromText('POINT({$rawDataCoords['lon']} {$rawDataCoords['lat']})', 4326)");
            }

            // Strategy 3: Get coordinates from related UGC POI
            if ($relatedUgcPoi && $relatedUgcPoi->geometry) {
                // get lon and lat from relatedUgcPoi->geometry
                $lon = $this->legacyDb->selectOne('SELECT ST_X(?) as lon', [$relatedUgcPoi->geometry])->lon;
                $lat = $this->legacyDb->selectOne('SELECT ST_Y(?) as lat', [$relatedUgcPoi->geometry])->lat;

                return DB::raw("ST_GeomFromText('POINT({$lon} {$lat})', 4326)");
            }

            // Strategy 4: Get coordinates from related UGC Track (centroid)
            if ($relatedUgcTrack && $relatedUgcTrack->geometry) {
                try {
                    $centroid = $this->legacyDb
                        ->selectOne('SELECT ST_AsEWKT(ST_Centroid(?)) as centroid', [$relatedUgcTrack->geometry])
                        ->centroid;

                    if ($centroid) {
                        return DB::raw("ST_GeomFromText('SRID=4326;POINT({$centroid})')");
                    }
                } catch (\Exception $e) {
                    $geometryErrorMessage = 'Error calculating centroid: '.$e->getMessage();
                }
            }

            // Strategy 5: Fallback to default point
            $this->logs['ugcMedia'][] = "[UGC Media ID: {$mediaId}] No valid geometry found, using default sea location";

            return DB::raw("ST_GeomFromText('SRID=4326;POINT(".self::DEFAULT_FALLBACK_POINT.")')");
        }

        // Log any geometry errors
        if ($geometryErrorMessage) {
            $this->logs['ugcMedia'][] = "[UGC Media ID: {$mediaId}] Geometry error: ".$geometryErrorMessage;
        }

        return $geometry;
    }

    /**
     * Check if geometry is at point zero (0,0)
     *
     * @param  mixed  $geometry
     */
    private function isPointZero($geometry): bool
    {
        return $this->legacyDb->selectOne('SELECT ST_AsText(?) as st_astext', [$geometry])->st_astext === 'POINT(0 0)';
    }

    /**
     * Convert non-point geometries to points (using centroid)
     *
     * @param  mixed  $geometry  Original geometry
     * @param  int  $mediaId  Media ID for error logging
     * @return mixed Normalized geometry or null if invalid
     */
    private function normalizeNonPointGeometry($geometry, int $mediaId)
    {
        $geometryType = $this->legacyDb
            ->selectOne('SELECT ST_GeometryType(?) as type', [$geometry])
            ->type;

        // If already a point, return unchanged
        if ($geometryType === 'ST_Point') {
            return $geometry;
        }

        // For non-point geometries, calculate centroid
        try {
            $centroid = $this->legacyDb
                ->selectOne('SELECT ST_AsEWKT(ST_Centroid(?)) as centroid', [$geometry])
                ->centroid;

            if ($centroid) {
                return $centroid;
            } else {
                $this->logs['ugcMedia'][] = "[UGC Media ID: {$mediaId}] Failed to calculate centroid for {$geometryType} geometry";

                return null;
            }
        } catch (\Exception $e) {
            $this->logs['ugcMedia'][] = "[UGC Media ID: {$mediaId}] Error calculating centroid: ".$e->getMessage();

            return null;
        }
    }

    /**
     * Extract coordinates from raw data
     *
     * @param  mixed  $rawData
     * @return array|null ['lat' => float, 'lon' => float] or null if not found
     */
    private function getCoordinatesFromRawData($rawData): ?array
    {
        if (! isset($rawData)) {
            return null;
        }

        $data = is_string($rawData) ? json_decode($rawData, true) : $rawData;

        if (isset($data['position']['latitude']) && isset($data['position']['longitude'])) {
            return [
                'lat' => $data['position']['latitude'],
                'lon' => $data['position']['longitude'],
            ];
        }

        return null;
    }

    /**
     * Check if media with the same geohub_id already exists
     *
     * @param  object  $media  Media object from legacy database
     * @return bool True if duplicate found
     */
    private function isDuplicateMedia(object $media): bool
    {
        if (empty($media->geohub_id)) {
            return false;
        }

        $existingMediaWithGeohubId = UgcMedia::where('geohub_id', $media->geohub_id)->first();

        return $existingMediaWithGeohubId && $existingMediaWithGeohubId->id != $media->id;
    }

    /**
     * Import UGC tracks from legacy database
     */
    private function importUgcTracks(): void
    {
        $query = $this->legacyDb->table('ugc_tracks');
        $query = $this->option('id') ? $query->where('id', $this->option('id')) : $query;
        $legacyTracks = $query->get();

        $this->info('Starting UGC Tracks import. Total to process: '.$legacyTracks->count());

        foreach ($legacyTracks as $track) {
            try {
                $trackUser = $this->ensureUserExists($track->user_id);
                $validGeometry = $this->validateTrackGeometry($track->id);

                if (! $validGeometry) {
                    $this->logs['invalidGeometries'][] = "UGC_TRACK ID: {$track->id} - Invalid or unsupported geometry";
                    $this->warn("Invalid or unsupported geometry for track ID: {$track->id}. Skipping...");

                    continue;
                }

                $this->info('Importing UGC track: '.$track->id);

                UgcTrack::updateOrCreate(
                    ['id' => $track->id],
                    [
                        'geohub_id' => $track->geohub_id,
                        'created_at' => $track->created_at,
                        'updated_at' => now(),
                        'name' => $track->name,
                        'description' => $track->description,
                        'geometry' => $validGeometry,
                        'user_id' => $trackUser->id ?? null,
                        'raw_data' => $this->normalizeRawData($track->raw_data),
                        'taxonomy_wheres' => $track->taxonomy_wheres,
                        'metadata' => $track->metadata,
                        'app_id' => $track->app_id,
                        'validated' => $track->validated,
                        'validator_id' => $track->validator_id,
                        'validation_date' => $track->validation_date,
                    ]
                );
            } catch (\Exception $e) {
                $this->error("Error importing track ID {$track->id}: ".$e->getMessage());
            }
        }

        $this->info('UGC Tracks import completed');
    }

    /**
     * Validate and extract track geometry
     *
     * @param  int  $trackId  Track ID
     * @return mixed Valid geometry or null if invalid
     */
    private function validateTrackGeometry(int $trackId)
    {
        $geometryCheck = $this->legacyDb->selectOne(
            "SELECT ST_AsEWKT(
                CASE 
                    WHEN ST_IsValid(geometry) 
                        AND ST_GeometryType(geometry) IN ('ST_MultiLineString', 'ST_LineString')
                        THEN ST_Force3D(geometry)
                    ELSE NULL
                END
            ) as geometry 
            FROM ugc_tracks 
            WHERE id = ?",
            [$trackId]
        );

        return $geometryCheck && $geometryCheck->geometry ? $geometryCheck->geometry : null;
    }

    /**
     * Import UGC POIs from legacy database
     */
    private function importUgcPois(): void
    {
        $query = $this->legacyDb->table('ugc_pois');
        $query = $this->option('id') ? $query->where('id', $this->option('id')) : $query;
        $legacyPois = $query->get();

        $this->info('Starting UGC POIs import. Total to process: '.$legacyPois->count());

        foreach ($legacyPois as $poi) {
            try {
                $poiUser = $this->ensureUserExists($poi->user_id);
                $poiValidator = $this->ensureUserExists($poi->validator_id);
                $validGeometry = $this->validatePoiGeometry($poi->id);

                if (! $validGeometry) {
                    $this->logs['invalidGeometries'][] = "UGC_POI ID: {$poi->id} - Invalid or unsupported geometry";
                    $this->warn("Invalid or unsupported geometry for POI ID: {$poi->id}. Skipping...");

                    continue;
                }

                $this->info('Importing UGC POI: '.$poi->id);

                UgcPoi::updateOrCreate(
                    ['id' => $poi->id],
                    [
                        'geohub_id' => $poi->geohub_id,
                        'created_at' => $poi->created_at,
                        'updated_at' => now(),
                        'name' => $poi->name,
                        'description' => $poi->description,
                        'geometry' => $validGeometry,
                        'user_id' => $poiUser->id ?? null,
                        'raw_data' => $this->normalizeRawData($poi->raw_data),
                        'taxonomy_wheres' => $poi->taxonomy_wheres,
                        'form_id' => $poi->form_id,
                        'validated' => $poi->validated,
                        'water_flow_rate_validated' => $poi->water_flow_rate_validated,
                        'validation_date' => $poi->validation_date,
                        'validator_id' => $poiValidator->id ?? null,
                        'note' => $poi->note,
                        'app_id' => $poi->app_id,
                    ]
                );
            } catch (\Exception $e) {
                $this->error("Error importing POI ID {$poi->id}: ".$e->getMessage());
            }
        }

        $this->info('UGC POIs import completed');
    }

    /**
     * Validate and extract POI geometry
     *
     * @param  int  $poiId  POI ID
     * @return mixed Valid geometry or null if invalid
     */
    private function validatePoiGeometry(int $poiId)
    {
        $geometryCheck = $this->legacyDb->selectOne(
            "SELECT ST_AsEWKT(
                CASE 
                    WHEN ST_IsValid(geometry) 
                        AND ST_GeometryType(geometry) = 'ST_Point'
                        THEN ST_Force2D(geometry)
                    ELSE NULL
                END
            ) as geometry 
            FROM ugc_pois 
            WHERE id = ?",
            [$poiId]
        );

        return $geometryCheck && $geometryCheck->geometry ? $geometryCheck->geometry : null;
    }

    /**
     * Normalize raw data to ensure consistent format
     *
     * @param  mixed  $rawData
     * @return array|null
     */
    private function normalizeRawData($rawData)
    {
        if (! isset($rawData)) {
            return null;
        }

        return is_string($rawData) ? json_decode($rawData, true) : $rawData;
    }

    /**
     * Extract coordinates from EXIF data of an image
     *
     * @param  string  $imageUrl  Image URL
     * @return array|null ['lat' => float, 'lon' => float] or null if not found
     */
    private function getExifCoordinates($imageUrl): ?array
    {
        try {
            // Read EXIF data with error handling
            $exif = @exif_read_data($imageUrl, 0, true);

            if (! $exif) {
                $this->info("No EXIF data found in: {$imageUrl}");

                return null;
            }

            $this->info("EXIF data found for: {$imageUrl}");

            // Method 1: Standard structure with GPS section
            if (isset($exif['GPS'])) {
                return $this->parseStandardGpsExif($exif['GPS']);
            }
            // Method 2: Alternative structure with coordinates at the main level
            elseif (isset($exif['GPSLatitude']) && isset($exif['GPSLongitude'])) {
                return $this->parseDirectGpsExif($exif);
            }
            // Method 3: Recursively explore for coordinates
            else {
                return $this->exploreExifForGps($exif);
            }
        } catch (\Exception $e) {
            $this->error('Error extracting EXIF data: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Parse GPS data in standard format
     *
     * @param  array  $gpsData  GPS data section from EXIF
     * @return array|null ['lat' => float, 'lon' => float] or null if invalid
     */
    private function parseStandardGpsExif($gpsData): ?array
    {
        if (isset($gpsData['GPSLatitude']) && isset($gpsData['GPSLongitude'])) {
            $lat = $this->convertDMSToDecimal($gpsData['GPSLatitude'], $gpsData['GPSLatitudeRef'] ?? 'N');
            $lon = $this->convertDMSToDecimal($gpsData['GPSLongitude'], $gpsData['GPSLongitudeRef'] ?? 'E');

            if ($this->areValidCoordinates($lat, $lon)) {
                return ['lat' => $lat, 'lon' => $lon];
            }
        }

        return null;
    }

    /**
     * Parse direct GPS coordinates (not in GPS section)
     *
     * @param  array  $exif  Full EXIF data
     * @return array|null ['lat' => float, 'lon' => float] or null if invalid
     */
    private function parseDirectGpsExif($exif): ?array
    {
        if (isset($exif['GPSLatitude']) && isset($exif['GPSLongitude'])) {
            $latRef = isset($exif['GPSLatitudeRef']) ? $exif['GPSLatitudeRef'] : 'N';
            $lonRef = isset($exif['GPSLongitudeRef']) ? $exif['GPSLongitudeRef'] : 'E';

            $lat = $this->convertDMSToDecimal($exif['GPSLatitude'], $latRef);
            $lon = $this->convertDMSToDecimal($exif['GPSLongitude'], $lonRef);

            if ($this->areValidCoordinates($lat, $lon)) {
                return ['lat' => $lat, 'lon' => $lon];
            }
        }

        return null;
    }

    /**
     * Recursively explore EXIF data for GPS coordinates
     *
     * @param  array  $exif  EXIF data to explore
     * @return array|null ['lat' => float, 'lon' => float] or null if not found
     */
    private function exploreExifForGps($exif): ?array
    {
        foreach ($exif as $section) {
            if (is_array($section)) {
                // Check if coordinates are in this section
                if (isset($section['GPSLatitude']) && isset($section['GPSLongitude'])) {
                    $latRef = $section['GPSLatitudeRef'] ?? 'N';
                    $lonRef = $section['GPSLongitudeRef'] ?? 'E';

                    $lat = $this->convertDMSToDecimal($section['GPSLatitude'], $latRef);
                    $lon = $this->convertDMSToDecimal($section['GPSLongitude'], $lonRef);

                    if ($this->areValidCoordinates($lat, $lon)) {
                        return ['lat' => $lat, 'lon' => $lon];
                    }
                }

                // Check subsections recursively
                $result = $this->exploreExifForGps($section);
                if ($result) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Check if coordinates are valid (not 0,0 and within bounds)
     *
     * @param  float  $lat  Latitude
     * @param  float  $lon  Longitude
     */
    private function areValidCoordinates(float $lat, float $lon): bool
    {
        return ($lat != 0 || $lon != 0) &&
            $lat >= -90 && $lat <= 90 &&
            $lon >= -180 && $lon <= 180;
    }

    /**
     * Convert DMS (Degrees, Minutes, Seconds) to decimal
     *
     * @param  array|string  $dmsArray  DMS coordinates
     * @param  string  $ref  Direction reference (N/S/E/W)
     * @return float Decimal coordinate
     */
    private function convertDMSToDecimal($dmsArray, string $ref): float
    {
        if (! is_array($dmsArray) || count($dmsArray) < 3) {
            return 0;
        }

        $degrees = $this->fractionToFloat($dmsArray[0]);
        $minutes = $this->fractionToFloat($dmsArray[1]);
        $seconds = $this->fractionToFloat($dmsArray[2]);

        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

        // Make negative for South or West
        if ($ref == 'S' || $ref == 'W') {
            $decimal = -$decimal;
        }

        return $decimal;
    }

    /**
     * Convert fraction to float
     *
     * @param  mixed  $fraction  Fraction as string "10/3" or array [10, 3]
     */
    private function fractionToFloat($fraction): float
    {
        // Handle string fractions like "10/3"
        if (is_string($fraction) && strpos($fraction, '/') !== false) {
            $parts = explode('/', $fraction);
            if (isset($parts[1]) && (float) $parts[1] != 0) {
                return (float) $parts[0] / (float) $parts[1];
            }

            return 0;
        }

        // Handle array fractions like [10, 3]
        if (is_array($fraction) && isset($fraction[0]) && isset($fraction[1]) && (float) $fraction[1] != 0) {
            return (float) $fraction[0] / (float) $fraction[1];
        }

        // Return direct value if already a number
        return (float) $fraction;
    }
}
