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
    protected $signature = 'osm2cai:sync-ugc {--model= : The model to sync (pois/tracks/media)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync UGC from legacy OSM2CAI';

    /**
     * Legacy database connection
     */
    private $legacyDb;

    /**
     * Cache for users to avoid multiple DB queries
     */
    private $userCache = [];

    /**
     * Log for invalid geometries
     */
    private $invalidGeometriesLog = [];

    public function __construct()
    {
        parent::__construct();
        $this->legacyDb = DB::connection('legacyosm2cai');
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $this->invalidGeometriesLog = [];

        $model = $this->option('model');
        $importMethods = [
            'pois' => 'importUgcPois',
            'tracks' => 'importUgcTracks',
            'media' => 'importUgcMedia',
        ];

        if (! $model) {
            $this->importAll();
        }
        if (! in_array($model, ['pois', 'tracks', 'media'])) {
            $this->error('Invalid model: '.$model);

            return;
        }

        if (isset($importMethods[$model])) {
            $this->{$importMethods[$model]}();
        }

        if (! empty($this->invalidGeometriesLog)) {
            $content = implode("\n", $this->invalidGeometriesLog);
            Storage::put('invalid_geometries_ugc.txt', $content);
            $this->info('Invalid geometries have been logged to invalid_geometries_ugc.txt');
        }

        Log::info('SyncUgcFromLegacyOsm2caiCommand finished');
    }

    /**
     * Import all UGC types (pois, tracks, media)
     *
     * @return void
     */
    private function importAll(): void
    {
        $this->importUgcPois();
        $this->importUgcTracks();
        $this->importUgcMedia();
    }

    /**
     * Ensure user exists in the system, create if not found
     *
     * @param int|null $userId The user ID to check
     * @return User|null
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
            $this->error('User not found: '.$userId ?? 'empty user_id');
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
     * Create a new user from legacy user data
     *
     * @param object $legacyUser The legacy user data
     * @return User
     */
    private function createUser(object $legacyUser): User
    {
        $user = new User();
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
     *
     * @return void
     */
    private function importUgcMedia(): void
    {
        $legacyMedia = $this->legacyDb->table('ugc_media')->get();

        foreach ($legacyMedia as $media) {
            $userId = $media->user_id;
            $ugcPoiId = null;
            $ugcTrackId = null;
            $mediaUser = $this->ensureUserExists($userId);

            $poiRelation = $this->legacyDb
                ->table('ugc_media_ugc_poi')
                ->where('ugc_media_id', $media->id)
                ->first();

            if ($poiRelation) {
                $ugcPoi = UgcPoi::find($poiRelation->ugc_poi_id);
                if ($ugcPoi) {
                    $ugcPoiId = $ugcPoi->id;
                }
            }

            $trackRelation = $this->legacyDb
                ->table('ugc_media_ugc_track')
                ->where('ugc_media_id', $media->id)
                ->first();

            if ($trackRelation) {
                $ugcTrack = UgcTrack::find($trackRelation->ugc_track_id);
                if ($ugcTrack) {
                    $ugcTrackId = $ugcTrack->id;
                }
            }

            $this->info('Importing UGC media: '.$media->id);
            $imageUrl = strpos($media->relative_url, 'http') === 0 ? $media->relative_url : "https://osm2cai.cai.it/storage/{$media->relative_url}";

            if (! str_starts_with($imageUrl, 'https://geohub.webmapp.it/')) {
                $imageContent = Http::get($imageUrl)->body();
                $imagePath = 'ugc-media/'.basename($media->relative_url);
                //check if the image already exists
                if (! Storage::disk('public')->exists($imagePath)) {
                    Storage::disk('public')->put($imagePath, $imageContent);
                }
            }

            // Check geometry type and calculate centroid if needed (there is one media with type linestring https://osm2cai.cai.it/resources/ugc-medias/5270)
            $geometry = $media->geometry;
            if ($geometry) {
                $geometryType = $this->legacyDb
                    ->selectOne('SELECT ST_GeometryType(?) as type', [$geometry])
                    ->type;

                if ($geometryType !== 'ST_Point') {
                    $this->invalidGeometriesLog[] = sprintf(
                        'UGC_MEDIA ID: %d - Invalid geometry type: %s',
                        $media->id,
                        $geometryType
                    );
                    continue;
                }
            }

            UgcMedia::updateOrCreate(['id' => $media->id], [
                'geohub_id' => $media->geohub_id,
                'created_at' => $media->created_at,
                'updated_at' => now(),
                'name' => $media->name,
                'description' => $media->description,
                'geometry' => $geometry,
                'user_id' => $mediaUser->id ?? null,
                'ugc_poi_id' => $ugcPoiId,
                'ugc_track_id' => $ugcTrackId,
                'raw_data' => is_string($media->raw_data) ? json_decode($media->raw_data, true) : $media->raw_data,
                'taxonomy_wheres' => $media->taxonomy_wheres,
                'relative_url' => $media->relative_url,
                'app_id' => $media->app_id,
            ]);
        }
    }

    /**
     * Import UGC tracks from legacy database
     *
     * @return void
     */
    private function importUgcTracks(): void
    {
        $legacyTracks = $this->legacyDb->table('ugc_tracks')->get();

        foreach ($legacyTracks as $track) {
            try {
                $userId = $track->user_id;
                $trackUser = $this->ensureUserExists($userId);

                // Check if geometry is valid and of the correct type
                $geometryCheck = $this->legacyDb
                    ->selectOne(
                        "
                        SELECT ST_AsEWKT(
                            CASE 
                                WHEN ST_IsValid(geometry) 
                                    AND ST_GeometryType(geometry) IN ('ST_MultiLineString', 'ST_LineString')
                                    THEN ST_Force3D(geometry)
                                ELSE NULL
                            END
                        ) as geometry 
                        FROM ugc_tracks 
                        WHERE id = ?",
                        [$track->id]
                    );

                if (! $geometryCheck || ! $geometryCheck->geometry) {
                    $this->invalidGeometriesLog[] = sprintf(
                        'UGC_TRACK ID: %d - Invalid or unsupported geometry',
                        $track->id
                    );
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
                        'geometry' => $geometryCheck->geometry,
                        'user_id' => $trackUser->id ?? null,
                        'raw_data' => is_string($track->raw_data) ? json_decode($track->raw_data, true) : $track->raw_data,
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
                continue;
            }
        }
    }

    /**
     * Import UGC POIs from legacy database
     *
     * @return void
     */
    private function importUgcPois(): void
    {
        $legacyUgc = $this->legacyDb->table('ugc_pois')->get();

        foreach ($legacyUgc as $ugc) {
            try {
                $userId = $ugc->user_id;
                $poiUser = $this->ensureUserExists($userId);
                $poiValidator = $this->ensureUserExists($ugc->validator_id);

                // Check if geometry is valid and of the correct type
                $geometryCheck = $this->legacyDb
                    ->selectOne(
                        "
                        SELECT ST_AsEWKT(
                            CASE 
                                WHEN ST_IsValid(geometry) 
                                    AND ST_GeometryType(geometry) = 'ST_Point'
                                    THEN ST_Force2D(geometry)
                                ELSE NULL
                            END
                        ) as geometry 
                        FROM ugc_pois 
                        WHERE id = ?",
                        [$ugc->id]
                    );

                if (! $geometryCheck || ! $geometryCheck->geometry) {
                    $this->invalidGeometriesLog[] = sprintf(
                        'UGC_POI ID: %d - Invalid or unsupported geometry',
                        $ugc->id
                    );
                    $this->warn("Invalid or unsupported geometry for POI ID: {$ugc->id}. Skipping...");
                    continue;
                }

                $this->info('Importing UGC POI: '.$ugc->id);

                UgcPoi::updateOrCreate(
                    ['id' => $ugc->id],
                    [
                        'geohub_id' => $ugc->geohub_id,
                        'user_id' => $poiUser->id ?? null,
                        'name' => $ugc->name,
                        'description' => $ugc->description,
                        'geometry' => $geometryCheck->geometry,
                        'raw_data' => is_string($ugc->raw_data) ? json_decode($ugc->raw_data, true) : $ugc->raw_data,
                        'taxonomy_wheres' => $ugc->taxonomy_wheres,
                        'form_id' => $ugc->form_id,
                        'validated' => $ugc->validated,
                        'water_flow_rate_validated' => $ugc->water_flow_rate_validated,
                        'validation_date' => $ugc->validation_date,
                        'validator_id' => $poiValidator->id ?? null, // id is the same as legacy (imported in command App\Console\Commands\SyncUsersFromLegacyOsm2cai)
                        'note' => $ugc->note,
                        'app_id' => $ugc->app_id,
                        'created_at' => $ugc->created_at,
                        'updated_at' => now(),
                    ]
                );
            } catch (\Exception $e) {
                $this->error("Error importing POI ID {$ugc->id}: ".$e->getMessage());
                continue;
            }
        }
    }
}
