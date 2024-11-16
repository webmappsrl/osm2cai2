<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileIDWithLegacyOsm2cai extends Command
{
    protected $signature = 'osm2cai2:reconcile-id-with-legacy-osm2cai {--model=}';

    protected $description = 'Reconcile the IDs of the models with the legacy osm2cai database for consistency in the API';

    public function handle()
    {
        $model = $this->option('model');
        if (! $model) {
            $this->error('The model option is required');

            return;
        }
        $class = 'App\\Models\\'.$model;

        // Get model table in database
        $modelTable = (new $class)->getTable();
        // Connect to the legacy osm2cai database
        $legacyOsm2caiDb = DB::connection('legacyosm2cai');

        // Check if the table exists in the legacy database
        if (! $legacyOsm2caiDb->getSchemaBuilder()->hasTable($modelTable)) {
            $this->error('The table '.$modelTable.' does not exist in the legacy osm2cai database');

            return;
        }

        // Match the records by osmfeatures_id
        $legacyRecords = $legacyOsm2caiDb->table($modelTable)->pluck('osmfeatures_id', 'id')->toArray();
        $modelRecords = $class::whereIn('osmfeatures_id', array_values($legacyRecords))->get();

        // Update the records
        foreach ($modelRecords as $record) {
            $legacyId = array_search($record->osmfeatures_id, $legacyRecords);
            if ($legacyId !== false) {
                $record->update(['id' => $legacyId]);
            }
        }

        $this->info('Reconciliation completed successfully.');
    }
}
