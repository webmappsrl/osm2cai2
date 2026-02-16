<?php

namespace App\Console\Commands;

use App\Models\UgcPoi;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

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


    protected $uuids = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $execute = boolval($this->option('execute'));
        if ($execute) {
            $this->info('Running in report-only mode (no updates or deletions). Use --execute=1 to apply fixes.');
        }

        $duplicatedArray = [];
        $updatedCount = 0;
        $deletedCount = 0;
        $idMismatchedCount = 0;
        foreach (UgcPoi::all() as $ugcPoi) {

            $properties = $ugcPoi->properties;
            $uuid = Arr::get($properties, 'uuid');
            if (!$uuid || array_key_exists($uuid, $this->uuids)) {
                $isUpdatedPoi = $this->updatePropertiesId($ugcPoi,$execute);
                if ($isUpdatedPoi) {
                    $updatedCount++;
                }
                continue;
            }

            $duplicateUuids = UgcPoi::where('properties->uuid', $uuid)
                ->orderBy('updated_at', 'desc')
                ->get();

            $singleDuplicatedCount = 0;
            foreach ($duplicateUuids as $duplicatedUgcPoi) {
                if ($singleDuplicatedCount > 0) {
                    $duplicateProperties = $duplicatedUgcPoi->properties;
                    //$this->info($singleDuplicatedCount . ' - ' . $duplicatedUgcPoi->id . ' - ' . $duplicatedUgcPoi->updated_at . ' - ' . Arr::get($duplicateProperties, 'uuid'));
                    $duplicatedArray[$uuid] = $singleDuplicatedCount;
                    if ($execute) {
                        $duplicatedUgcPoi->deleteQuietly();
                        $deletedCount++;
                    }
                } else {
                    $isUpdatedPoi = $this->updatePropertiesId($duplicatedUgcPoi,$execute);
                    if ($isUpdatedPoi) {
                        $updatedCount++;
                    }
                }
                $singleDuplicatedCount++;
            }
            $this->uuids[$uuid] = $uuid;
        }
        sort($duplicatedArray);
        foreach ($duplicatedArray as $uuid => $count) {
            $this->info($uuid . ' - ' . $count);
        }
        $this->info('---------------------------------');
        $this->info('Report:');
        $this->info('Duplicated single UGC POIs count: ' . count($duplicatedArray));
        $this->info('Duplicated total UGC POIs count: ' . array_sum($duplicatedArray));
        $this->info('---------------------------------');
        if ($execute) {
            $this->info('Execution:');
            $this->info('Updated UGC POIs count: ' . $updatedCount);
            $this->info('Deleted UGC POIs count: ' . $deletedCount);
            $this->info('---------------------------------');
        }
    }

    protected function updatePropertiesId(UgcPoi $ugcPoi, $execute = false) {
        $properties = $ugcPoi->properties;
//        $this->info("PROPERTIES ID: " . Arr::get($properties,'id') . ' - MODEL ID: ' . $ugcPoi->id);
        if (Arr::get($properties, 'id') != $ugcPoi->id) {
            $properties['id'] = $ugcPoi->id;
            $ugcPoi->properties = $properties;
            if ($execute) {
                $ugcPoi->save();
            }
            return true;
        }
        return false;
    }
}
