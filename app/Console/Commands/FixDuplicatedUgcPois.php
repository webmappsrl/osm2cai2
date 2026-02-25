<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\UgcPoi;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Wm\WmPackage\Models\Media;

class FixDuplicatedUgcPois extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:fix-duplicated-ugc-pois
                            {--execute=0 : Only report duplicates without updating or deleting (default: true). Use --execute=1 to apply fixes.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command gets all ugc pois with duplicated properties->uuid and updates them removing the duplicates';


    protected $logChannel = 'ugc';

    protected $keysToKeepTheOldest = [

        'id',
        'created_at',
        'properties.id',
        'geohub_id',
        'created_by',
    ];

    protected $prefix = 'https://wmfe.s3.eu-central-1.amazonaws.com/';//env('AWS_URL');
    protected $localPath = null;

    protected $duplicatedUser = null;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->localPath = storage_path('ugc/images/');
        $execute = boolval($this->option('execute'));
        if (!$execute) {
            $this->logInfo('Running in report-only mode (no updates or deletions). Use --execute=1 to apply fixes.');
        } else {
            $this->logInfo('Running in execution mode');
        }

        $this->duplicatedUser = User::where('email', 'duplicated-ugc-pois@webmapp.it')->first();
        if (!$this->duplicatedUser) {
            $this->logInfo('Creating user duplicated-ugc-pois');
            $this->duplicatedUser = User::create([
                'email' => 'duplicated-ugc-pois@webmapp.it',
                'name' => 'duplicated-ugc-pois',
                'password' => bcrypt('webmapp123'),
            ]);
            $this->duplicatedUser->assignRole(UserRole::Guest->value);
        }


        /*
        ESEMPI DI IDS DUPLICATI E NON:
            IDS (407,408,410,413,414) GEOHUB ID DIVERSI
            IDS (3817,3867) GEOHUB ID UGUALI
            IDS (7974,7975) GEOHUB ID NULLI
            IDS (2,3,4,5,6) OK
        */

        File::ensureDirectoryExists($this->localPath);


        $duplicatedArray = [];
        $totalDuplicatedCount = 0;
        $deletedCount = 0;
        foreach (UgcPoi::all()->pluck('properties.uuid', 'properties.uuid') as $uuid) {
//            if (!in_array($uuid, ['8d23b8de-3f28-4725-92db-361f4f51753b','25e8c545-5100-4f2f-a7a2-eefe8ad9b2be','363b6d9c-3b48-403b-8283-0b897efd764e'])) {
//                continue;
//            }
            if (!$uuid) {
                continue;
            }
            $duplicateUuids = UgcPoi::where('properties->uuid', $uuid)
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $singleDuplicatedTotal = $duplicateUuids->count();
            if ($singleDuplicatedTotal <= 1) {
                continue;
            }

            $singleDuplicatedCount = 0;
            $referenceUgcPoiInfo = [];
            $ids = [];
            foreach ($duplicateUuids as $duplicatedUgcPoi) {
                $duplicatedProperties = $duplicatedUgcPoi->properties;
                $media = $duplicatedUgcPoi->getMedia();
                $ids[] = $duplicatedUgcPoi->id;
                $ugcPoiInfo = [
                    'uuid' => $uuid,
                    'count' => $singleDuplicatedCount,
                    'id' => $duplicatedUgcPoi->id,
                    'media_ids' => $media->pluck('id')->all(),
                    'media_filenames' => $media->pluck('original_url')->all(),
                    'media_count' => $media->count(),
                    'created_at' => $duplicatedUgcPoi->created_at,
                    'updated_at' => $duplicatedUgcPoi->updated_at,
                    'geohub_id' => $duplicatedUgcPoi->geohub_id,
                    'name' => $duplicatedUgcPoi->name,
                    'description' => $duplicatedUgcPoi->description,
                    'user_id' => $duplicatedUgcPoi->user_id,
                    'raw_data' => $duplicatedUgcPoi->raw_data,
                    'taxonomy_wheres' => $duplicatedUgcPoi->taxonomy_wheres,
                    'form_id' => $duplicatedUgcPoi->form_id,
                    'validated' => $duplicatedUgcPoi->validated,
                    'water_flow_rate_validated' => $duplicatedUgcPoi->water_flow_rate_validated,
                    'validator_id' => $duplicatedUgcPoi->validator_id,
                    'validation_date' => $duplicatedUgcPoi->validation_date,
                    'note' => $duplicatedUgcPoi->note,
                    'app_id' => $duplicatedUgcPoi->app_id,
                    'properties' => $duplicatedProperties,
                    'created_by' => $duplicatedUgcPoi->created_by,
                    'geohub_app_id' => $duplicatedUgcPoi->geohub_app_id,
                    'geometry' => $duplicatedUgcPoi->geometry,
                    'deleted' => false,
                ];
                if ($singleDuplicatedCount == 0) {
                    $referenceUgcPoiInfo = $ugcPoiInfo;
                    $duplicatedArray[$uuid] = [$ugcPoiInfo];
                }
                if ($singleDuplicatedCount > 0) {
                    $ugcPoiInfo['deleted'] = true;
                    foreach ($ugcPoiInfo as $key => $value) {
                        switch ($key) {
                            case 'uuid':
                            case 'deleted':
                            case 'media_ids':
                            case 'media_filenames':
                                break;
                            case 'media_count':
                                foreach ($ugcPoiInfo['media_ids'] as $index => $mediaId) {
                                    if (!in_array($mediaId, $referenceUgcPoiInfo['media_ids'])) {
                                        $referenceUgcPoiInfo['media_ids'][] = $mediaId;
                                        $referenceUgcPoiInfo['media_filenames'][] = $ugcPoiInfo['media_filenames'][$index];
                                        $referenceUgcPoiInfo['media_count']++;
                                    }
                                }
                                break;
                            default:

                                $this->substitueValue($referenceUgcPoiInfo, $ugcPoiInfo, $key, $key);
                                break;


                        }
                    }
                    if ($execute) {
                        $this->removeDuplicatedUgcPoi($duplicatedUgcPoi, $referenceUgcPoiInfo);
                        $deletedCount++;
                    }
                    $totalDuplicatedCount++;
                    $duplicatedArray[$uuid][] = $ugcPoiInfo;
                }
                $singleDuplicatedCount++;
                if ($singleDuplicatedCount >= $singleDuplicatedTotal) {
                    $referenceUgcPoiInfo['ids'] = implode(',', $ids);
                    $referenceUgcPoiInfo['count'] = 'FINAL';
                    $duplicatedArray[$uuid][] = $referenceUgcPoiInfo;
                    if ($referenceUgcPoiInfo['media_count'] > 1) {
                        $mediaFilenames = $this->saveImagesFromAws($referenceUgcPoiInfo);
                        $referenceUgcPoiInfo = $this->checkImagesIdentical($referenceUgcPoiInfo, $mediaFilenames);
                        $referenceUgcPoiInfo['count'] = 'FINAL AFTER IMAGES CHECK';
                        $duplicatedArray[$uuid][] = $referenceUgcPoiInfo;
                    }
                    if ($execute) {
                        $this->mergeInfoToOriginalUgcPoi($referenceUgcPoiInfo);
                    }
                }
            }
        }

        $this->logInfo('---------------------------------');
        $this->logInfo('Report:');

        foreach ($duplicatedArray as $uuid => $ugcPoiInfos) {
            $this->logInfo($uuid . ' - N. records: ' . count($ugcPoiInfos));
            $this->logInfo('-------------------------');
            foreach ($ugcPoiInfos as $ugcPoiInfo) {
                /*
                $this->logInfo(
                    $ugcPoiInfo['count'].' - ID: '.$ugcPoiInfo['id'].' - UPDATED AT: '.$ugcPoiInfo['updated_at'].' - '.
                    ($ugcPoiInfo['deleted'] ? 'deleted' : 'not deleted').
                    ' - GEOHUB ID: '.$ugcPoiInfo['geohub_id'].' - CREATED AT (PROPERTIES: '.Arr::get($ugcPoiInfo['properties'], 'createdAt').')'
                );
                */

            }
            $this->logInfo('-------------------------');
            $this->logInfo('');
        }

        if (count($duplicatedArray) > 0) {
            $this->writeDuplicatedCsv($duplicatedArray);
        }

        $this->logInfo('Duplicated single UGC POIs count: ' . count($duplicatedArray));
        $this->logInfo('Duplicated total UGC POIs count: ' . $totalDuplicatedCount);
        $this->logInfo('---------------------------------');
        if ($execute) {
            $this->logInfo('Execution:');
            $this->logInfo('Deleted UGC POIs count: ' . $deletedCount);
            $this->logInfo('---------------------------------');
        }
    }

    


    /**
     * Aggiorna il riferimento con i valori del duplicato; per le chiavi in keysToKeepTheOldest mantiene il valore più vecchio.
     */
    protected function substitueValue(array &$referenceUgcPoiInfo, array &$ugcPoiInfo, string $key, string $fullKey): void
    {
        $value = $ugcPoiInfo[$key];
        if (!array_key_exists($key, $referenceUgcPoiInfo)) {
            $referenceUgcPoiInfo[$key] = $value;
            return;
        }

        if (is_array($ugcPoiInfo[$key]) && is_array($referenceUgcPoiInfo[$key])) {
            foreach (array_keys($ugcPoiInfo[$key]) as $subKey) {
                $this->substitueValue($referenceUgcPoiInfo[$key], $ugcPoiInfo[$key], $subKey, $key . '.' . $subKey);
            }
        } else {

            if (in_array($fullKey, $this->keysToKeepTheOldest) || $ugcPoiInfo[$key] == $referenceUgcPoiInfo[$key]) {
                //unset($ugcPoiInfo[$key]);
            } else {
                $referenceUgcPoiInfo[$key] = $ugcPoiInfo[$key];
            }
        }
    }

    /**
     * Verifica se due file immagine sono identici (stesso contenuto).
     * Restituisce true se i file hanno lo stesso hash (stessi byte).
     */
    protected function imagesAreIdenticalByHash(string $path1, string $path2): bool
    {
        if (!is_readable($path1) || !is_readable($path2)) {
            return false;
        }
        return hash_file('sha256', $path1) === hash_file('sha256', $path2);
    }

    /**
     * Confronta le immagini dei media per uuid: rimuove da referenceUgcPoiInfo i media che sono identici (stesso hash) ad uno già tenuto.
     */
    protected function checkImagesIdentical(array $referenceUgcPoiInfo, array $mediaFilenames): array
    {
        $uuid = $referenceUgcPoiInfo['uuid'];
        $path = $this->localPath . '/' . $uuid . '/';
        $mediaFilenamesToKeep = [];
        foreach ($referenceUgcPoiInfo['media_ids'] as $index => $mediaId) {
            $mediaFilename = Arr::get($mediaFilenames, $mediaId);
            if (!$mediaFilename) {
                unset($referenceUgcPoiInfo['media_ids'][$index]);
                unset($referenceUgcPoiInfo['media_filenames'][$index]);
                continue;
            }
            $trovato = false;
            if (file_exists($path . $mediaFilename)) {
                foreach ($mediaFilenamesToKeep as $mediaFilenameCurrent) {
                    if ($this->imagesAreIdenticalByHash($path . $mediaFilename, $path . $mediaFilenameCurrent)) {
                        unset($referenceUgcPoiInfo['media_ids'][$index]);
                        unset($referenceUgcPoiInfo['media_filenames'][$index]);
                        $trovato = true;
                        break;
                    }
                }
                if (!$trovato) {
                    $mediaFilenamesToKeep[$mediaId] = $mediaFilename;
                }
            }

        }
        $referenceUgcPoiInfo['media_count'] = count($referenceUgcPoiInfo['media_ids']);
        return $referenceUgcPoiInfo;
    }

    /**
     * Scarica da AWS le immagini dei media del riferimento e le salva in localPath/uuid; restituisce media_id => filename.
     */
    protected function saveImagesFromAws(array $referenceUgcPoiInfo): array
    {
        $mediaFilenames = [];
        $uuid = $referenceUgcPoiInfo['uuid'];
        $path = $this->localPath . '/' . $uuid . '/';
        if (File::isDirectory($path)) {
            $files = File::allFiles($path);
            foreach ($files as $file) {
                $filename = $file->getBasename();
                $mediaId = Str::before($filename, '_');
                $mediaFilenames[$mediaId] = $filename;
                $this->info('Found image: ' . $filename);

            }
            return $mediaFilenames;
        }
        File::ensureDirectoryExists($path);

        foreach ($referenceUgcPoiInfo['media_ids'] as $index => $mediaId) {
            $url = $referenceUgcPoiInfo['media_filenames'][$index];
            $url = Str::after($url, '//');
            $url = Str::after($url, '/');
            $url = $this->prefix . $url;
            $this->info('Downloading image: ' . $url);
            $content = Http::get($url)->body();  // stringa con il contenuto (raw bytes per le immagini)

            $mediaFilename = $mediaId . '_' . basename($url);
            File::put($path . $mediaFilename, $content);
            $mediaFilenames[$mediaId] = $mediaFilename;
        }

        return $mediaFilenames;
    }

    /**
     * Applica i dati uniti (referenceUgcPoiInfo) al POI originale e riassegna i media al modello originale.
     */
    protected function mergeInfoToOriginalUgcPoi(array $referenceUgcPoiInfo): void
    {
        $originalUgcPoi = UgcPoi::find($referenceUgcPoiInfo['id']);
        if (!$originalUgcPoi) {
            return;
        }
        $fields = [
                    'updated_at',
                    'name',
                    'description',
                    'user_id',
                    'raw_data',
                    'taxonomy_wheres',
                    'form_id',
                    'validated',
                    'water_flow_rate_validated',
                    'validator_id',
                    'validation_date',
                    'note',
                    'app_id',
                    'properties',
                    'geohub_app_id',
                    'geometry',
        ];
        $originalUgcPoi->fill(Arr::only($referenceUgcPoiInfo, $fields));
        $properties = $originalUgcPoi->properties;
        $properties['has_been_duplicated'] = true;
        $properties['duplication_info'] = [
            'ids' => $referenceUgcPoiInfo['ids'],
            'count' => count(explode(',', $referenceUgcPoiInfo['ids'])),
        ];
        $originalUgcPoi->properties = $properties;
        $originalUgcPoi->timestamps = false;
        $originalUgcPoi->saveQuietly();
        $originalUgcPoi->timestamps = true;
        $mediaIds = $referenceUgcPoiInfo['media_ids'];
        foreach ($mediaIds as $mediaId) {
            $media = Media::find($mediaId);
            if (!$media) {
                continue;
            }
            $media->model_id = $originalUgcPoi->id;
            $media->saveQuietly();
        }


    }

    /**
     * Marca il POI duplicato come tale (properties.duplicated, user_id = duplicatedUser) e salva senza aggiornare i timestamps.
     */
    protected function removeDuplicatedUgcPoi(UgcPoi $duplicatedUgcPoi, $referenceUgcPoiInfo): void
    {
        $properties = $duplicatedUgcPoi->properties;
        $media = $duplicatedUgcPoi->getMedia();
        $properties['uuid'] = null;
        $properties['duplicated'] = true;
        $properties['duplication_info'] = [
            'uuid' => $referenceUgcPoiInfo['uuid'],
            'position' => $referenceUgcPoiInfo['count'],
            'parent_ugc_poi_id' => $referenceUgcPoiInfo['id'],
            'parent_ugc_poi_user_id' => $referenceUgcPoiInfo['user_id'],
            'media_ids' => $media->pluck('id')->all(),
            'media_filenames' => $media->pluck('original_url')->all(),
            'media_count' => $media->count(),
        ];
        $duplicatedUgcPoi->properties = $properties;
        $duplicatedUgcPoi->user_id = $this->duplicatedUser->id;
        $duplicatedUgcPoi->timestamps = false;
        $duplicatedUgcPoi->saveQuietly();
        $duplicatedUgcPoi->timestamps = true;
     //   $duplicatedUgcPoi->deleteQuietly();
    }


    /**
     * Scrive un CSV in storage/logs con i record duplicati.
     * Colonne: tutti i campi di ugcPoiInfo (tranne properties) + ogni sottocampo di properties come colonna a parte.
     * Separatore ";". Nome file: duplicatedUgcPoi-YYYY-MM-DD.csv
     */
    protected function writeDuplicatedCsv(array $duplicatedArray): void
    {
        $allPropertyKeys = [];
        $rows = [];
        $baseKeys = [
            'uuid', 'count', 'id', 'media_count', 'media_ids',
            //'media_filenames',
            'deleted', 'created_at', 'updated_at', 'geohub_id', 'name', 'description',
            'user_id', 'app_id', 'form_id', 'validated', 'water_flow_rate_validated', 'validator_id',
            'validation_date', 'note', 'taxonomy_wheres', 'created_by', 'geohub_app_id', 'raw_data', 'geometry',
        ];
        foreach ($duplicatedArray as $uuid => $ugcPoiInfos) {
            foreach ($ugcPoiInfos as $ugcPoiInfo) {
                $props = $ugcPoiInfo['properties'] ?? [];
                foreach (array_keys($props) as $key) {
                    if (!in_array($key, $allPropertyKeys, true)) {
                        $allPropertyKeys[] = $key;
                    }
                }
                $rows[] = [
                    'base' => $ugcPoiInfo,
                    'properties' => $props,
                ];
            }
        }

        if (count($rows) === 0) {
            return;
        }

        $header = array_merge($baseKeys, array_map(fn($key) => 'properties_' . $key, $allPropertyKeys));

        $filename = 'duplicatedUgcPoi-' . now()->format('Y-m-d') . '.csv';
        $path = storage_path('logs' . DIRECTORY_SEPARATOR . $filename);

        $handle = fopen($path, 'w');
        if ($handle === false) {
            $this->warn("Impossibile creare il file CSV: {$path}");
            Log::channel($this->logChannel)->warning("Impossibile creare il file CSV: {$path}");

            return;
        }

        fputcsv($handle, $header, ';');
        foreach ($rows as $row) {
            $base = $row['base'];
            $line = [];
            foreach ($baseKeys as $key) {
                $value = $base[$key] ?? null;
                if ($key === 'deleted') {
                    $line[] = !empty($value) ? '1' : '0';
                } elseif (in_array($key, ['media_ids', 'media_filenames'])) {
                    $line[] = implode(',', $value);
                } elseif ($value === null) {
                    $line[] = '';
                } elseif (is_array($value)) {
                    $line[] = json_encode($value);
                } elseif (is_object($value)) {
                    $line[] = (string)$value;
                } else {
                    $line[] = $value;
                }
            }
            foreach ($allPropertyKeys as $key) {
                $property = Arr::get($row['properties'], $key);
                $line[] = $property !== null && $property !== ''
                    ? (is_array($property) ? json_encode($property) : (string)$property)
                    : '';
            }
            fputcsv($handle, $line, ';');
        }
        fclose($handle);

        $this->logInfo("CSV scritto: {$path}");
    }

    /**
     * Scrive il messaggio su console (info) e sul canale log 'duplicated-ugc'.
     */
    protected function logInfo(string $message): void
    {
        $this->info($message);
        Log::channel($this->logChannel)->info($message);
    }
}
