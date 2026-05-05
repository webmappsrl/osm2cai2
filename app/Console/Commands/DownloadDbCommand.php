<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DownloadDbCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:download';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'download a dump.sql from server';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Log::info('db:download -> is started');
        $fileName = 'last_dump.sql.gz';
        $lastDumpRemotePath = "maphub/osm2cai2/$fileName";

        $wmdumps = Storage::disk('wmdumps');
        $backups = Storage::disk('backups');

        if (! $wmdumps->exists($lastDumpRemotePath)) {
            Log::error("db:download -> $lastDumpRemotePath does not exist");
            throw new Exception("db:download -> $lastDumpRemotePath does not exist");
        }

        Log::info('db:download -> START last-dump');
        $lastDump = $wmdumps->get($lastDumpRemotePath);

        if (! $lastDump) {
            Log::error("db:download -> $lastDumpRemotePath download error");
            throw new Exception("db:download -> $lastDumpRemotePath download error");
        }
        Log::info('db:download -> DONE last-dump');

        $backups->put($fileName, $lastDump);
        $localPath = $backups->path($fileName);

        if (! file_exists($localPath)) {
            Log::error('db:download -> save to storage/backups FAILED');
            throw new Exception('db:download -> save to storage/backups FAILED');
        }

        Log::info('db:download -> finished');

        return 0;
    }
}
