<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetIdSequenceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:reset-id-sequences';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset ID sequences in all tables based on the highest ID value';

    /**
     * List of tables that must be included in the reset
     *
     * @var array
     */
    protected $requiredTables = [
        'ugc_pois',
        'ugc_tracks',
        'ugc_media',
        'clubs',
        'sectors',
        'areas',
        'itineraries',
        'mountain_groups',
        'cai_huts',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tableNames = $this->requiredTables;

        // Verify tables exist and have ID column
        foreach ($tableNames as $key => $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'id')) {
                $this->warn("Required table {$tableName} does not exist or doesn't have an ID column");
                unset($tableNames[$key]);
            }
        }

        $this->info('Processing '.count($tableNames).' required tables');

        $bar = $this->output->createProgressBar(count($tableNames));
        $bar->start();

        foreach ($tableNames as $tableName) {
            $this->resetSequence($tableName);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('All ID sequences have been reset successfully!');
    }

    /**
     * Reset the ID sequence for a specific table.
     *
     * @param string $tableName
     * @return void
     */
    private function resetSequence($tableName)
    {
        // Get the highest ID from the table
        $result = DB::table($tableName)->max('id');
        $maxId = $result ?? 0;

        // PostgreSQL-specific sequence reset
        $sequenceName = $tableName.'_id_seq';

        try {
            DB::statement("ALTER SEQUENCE {$sequenceName} RESTART WITH ".($maxId + 1));
            $this->line("  - Reset sequence for table {$tableName} to ".($maxId + 1));
        } catch (\Exception $e) {
            $this->warn("  - Failed to reset sequence for table {$tableName}: ".$e->getMessage());
        }
    }
}
