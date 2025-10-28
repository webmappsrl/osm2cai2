<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->exec('certbot renew --quiet')
            ->dailyAt('02:00')
            ->description('Renew SSL certificates');

        $schedule->command('wm-osmfeatures:sync')
            ->dailyAt('02:00')
            ->description('Sync wm-osmfeatures');

        $schedule->command('wm-osmfeatures:import-sync')
            ->dailyAt('03:30')
            ->description('Import-sync wm-osmfeatures');

        $schedule->command('horizon:snapshot')
            ->hourlyAt(10)
            ->description('Take Horizon snapshot');

        $schedule->command('osm2cai:update-hiking-routes')
            ->dailyAt('05:00')
            ->description('Check osmfeatures for hiking routes updates');

        $schedule->command('osm2cai:check_hr_existence_on_osm')
            ->dailyAt('06:30')
            ->description('Check hiking routes existence on OSM');

        $schedule->command('osm2cai:fix-osmfeatures-sda')
            ->dailyAt('07:00')
            ->description('Update hiking routes status');

        /*$schedule->command('osm2cai:cache-mitur-abruzzo-api --all')
            ->weeklyOn(6, '09:00') // 6 = Saturday
            ->description('Cache Mitur Abruzzo API (Saturday)');

        // Refresh dashboard cache with fresh data once per day
        $schedule->command('osm2cai:refresh-dashboard-cache')
            ->dailyAt('08:00')
            ->description('Refresh dashboard cache with fresh data');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
