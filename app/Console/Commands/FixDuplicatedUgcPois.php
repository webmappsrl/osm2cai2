<?php

namespace App\Console\Commands;

use App\Models\UgcPoi;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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


    /**
     * Execute the console command.
     */
    public function handle()
    {
        $execute = boolval($this->option('execute'));
        if (! $execute) {
            $this->logInfo('Running in report-only mode (no updates or deletions). Use --execute=1 to apply fixes.');
        } else {
            $this->logInfo('Running in execution mode');
        }


        /*
        ESEMPI DI IDS DUPLICATI E NON:
            IDS (407,408,410,413,414) GEOHUB ID DIVERSI
            IDS (3817,3867) GEOHUB ID UGUALI
            IDS (7974,7975) GEOHUB ID NULLI
            IDS (2,3,4,5,6) OK
        */


        $duplicatedArray = [];
        $totalDuplicatedCount = 0;
        $deletedCount = 0;
        foreach (UgcPoi::all()->pluck('properties.uuid','properties.uuid') as $uuid) {
            if (!$uuid) {
                continue;
            }
            $duplicateUuids = UgcPoi::where('properties->uuid', $uuid)
                ->orderBy('updated_at', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            $singleDuplicatedCount = 0;
            foreach ($duplicateUuids as $duplicatedUgcPoi) {
                if ($singleDuplicatedCount == 1) {
                    $duplicatedArray[$uuid] = [$ugcPoiInfo];
                }
                $duplicatedProperties = $duplicatedUgcPoi->properties;
                $ugcPoiInfo = [
                    'count' => $singleDuplicatedCount,
                    'id' => $duplicatedUgcPoi->id,
                    'updated_at' => $duplicatedUgcPoi->updated_at,
                    'geohub_id' => $duplicatedUgcPoi->geohub_id,
                    'created_at' => $duplicatedUgcPoi->created_at,
                    'properties' => Arr::only($duplicatedProperties, ['id', 'uuid', 'app_id', 'device', 'createdAt', 'updatedAt']),
                    'deleted' => false,
                ];
                if ($singleDuplicatedCount > 0) {
                    $ugcPoiInfo['deleted'] = true;
                    if ($execute) {
                        $duplicatedUgcPoi->deleteQuietly();
                        $deletedCount++;
                    }
                    $totalDuplicatedCount++;
                    $duplicatedArray[$uuid][] = $ugcPoiInfo;
                } 
                $singleDuplicatedCount++;
            }
        }
        $this->logInfo('---------------------------------');
        $this->logInfo('Report:');

        foreach ($duplicatedArray as $uuid => $ugcPoiInfos) {
            $this->logInfo($uuid.' - N. records: '.count($ugcPoiInfos));
            $this->logInfo('-------------------------');
            foreach ($ugcPoiInfos as $ugcPoiInfo) {
                $this->logInfo(
                    $ugcPoiInfo['count'].' - ID: '.$ugcPoiInfo['id'].' - UPDATED AT: '.$ugcPoiInfo['updated_at'].' - '.
                    ($ugcPoiInfo['deleted'] ? 'deleted' : 'not deleted').
                    ' - GEOHUB ID: '.$ugcPoiInfo['geohub_id'].' - CREATED AT (PROPERTIES: '.Arr::get($ugcPoiInfo['properties'], 'createdAt').')'
                );
            }
            $this->logInfo('-------------------------');
            $this->logInfo('');
        }

        if (count($duplicatedArray) > 0) {
            $this->writeDuplicatedCsv($duplicatedArray);
        }

        $this->logInfo('Duplicated single UGC POIs count: '.count($duplicatedArray));
        $this->logInfo('Duplicated total UGC POIs count: '.$totalDuplicatedCount);
        $this->logInfo('---------------------------------');
        if ($execute) {
            $this->logInfo('Execution:');
            $this->logInfo('Deleted UGC POIs count: '.$deletedCount);
            $this->logInfo('---------------------------------');
        }
    }

    /**
     * Scrive un CSV in storage/logs con i record duplicati.
     * Ogni sottocampo di properties è una colonna; separatore ";".
     * Nome file: duplicatedUgcPoi-YYYY-MM-DD.csv
     */
    protected function writeDuplicatedCsv(array $duplicatedArray): void
    {
        $allPropertyKeys = [];
        $rows = [];
        foreach ($duplicatedArray as $uuid => $ugcPoiInfos) {
            foreach ($ugcPoiInfos as $ugcPoiInfo) {
                $props = $ugcPoiInfo['properties'] ?? [];
                foreach (array_keys($props) as $key) {
                    if (! in_array($key, $allPropertyKeys, true)) {
                        $allPropertyKeys[] = $key;
                    }
                }
                $rows[] = [
                    'model_id' => $ugcPoiInfo['id'],
                    'model_updated_at' => $ugcPoiInfo['updated_at'] ?? '',
                    'model_created_at' => $ugcPoiInfo['created_at'] ?? '',
                    'geohub_id' => $ugcPoiInfo['geohub_id'] ?? '',
                    'count' => $ugcPoiInfo['count'] ?? '',
                    'deleted' => $ugcPoiInfo['deleted'] ? '1' : '0',
                    'properties' => $props,
                ];
            }
        }

        if (count($rows) === 0) {
            return;
        }

        $baseHeader = ['model_id', 'model_updated_at', 'model_created_at', 'geohub_id', 'count', 'deleted'];
        $propertiesHeaders = array_map(function ($key) {
            return 'properties_'.$key;
        }, $allPropertyKeys);
        $header = array_merge($baseHeader, $propertiesHeaders);

        $filename = 'duplicatedUgcPoi-'.now()->format('Y-m-d').'.csv';
        $path = storage_path('logs'.DIRECTORY_SEPARATOR.$filename);

        $handle = fopen($path, 'w');
        if ($handle === false) {
            $this->warn("Impossibile creare il file CSV: {$path}");
            Log::channel('duplicated-ugc')->warning("Impossibile creare il file CSV: {$path}");

            return;
        }

        fputcsv($handle, $header, ';');
        foreach ($rows as $row) {
            $line = [
                $row['model_id'],
                $row['model_updated_at'],
                $row['model_created_at'],
                $row['geohub_id'],
                $row['count'],
                $row['deleted'],
            ];
            foreach ($allPropertyKeys as $key) {
                $property = Arr::get($row['properties'],$key);
                $line[] = $property ? (is_array($property) ? json_encode($property) : $property) : '';
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
        Log::channel('duplicated-ugc')->info($message);
    }

}
