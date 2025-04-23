<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {

        // osm2cai:import-ugc-from-geohub
        $schedule->exec('docker exec -it php81_osm2cai2 php artisan osm2cai:import-ugc-from-geohub')
            ->dailyAt('00:00')
            ->appendOutputTo('/var/log/laravel-cron.log')
            ->description('Sync UGC from Geohub');

        // Certbot renew
        $schedule->exec('certbot renew --quiet')
            ->dailyAt('02:00')
            ->description('Renew SSL certificates');

        // wm-osmfeatures:sync
        $schedule->exec('docker exec -i php81_osm2cai2 php artisan wm-osmfeatures:sync')
            ->dailyAt('02:00')
            ->appendOutputTo('/var/log/laravel-cron.log')
            ->description('Sync wm-osmfeatures');

        // wm-osmfeatures:import-sync
        $schedule->exec('docker exec -i php81_osm2cai2 php artisan wm-osmfeatures:import-sync')
            ->dailyAt('03:30')
            ->appendOutputTo('/var/log/laravel-cron.log')
            ->description('Import-sync wm-osmfeatures');

        // Horizon snapshot
        $schedule->exec('docker exec -i php81_osm2cai2 php artisan horizon:snapshot')
            ->hourlyAt(10)
            ->description('Take Horizon snapshot');

        // osm2cai:update-hiking-routes
        $schedule->exec('docker exec -i php81_osm2cai2 php artisan osm2cai:update-hiking-routes')
            ->dailyAt('05:00')
            ->appendOutputTo('/var/log/laravel-cron.log')
            ->description('Check osmfeatures for hiking routes updates');

        // osm2cai:check_hr_existence_on_osm
        $schedule->exec('docker exec -it php81_osm2cai2 php artisan osm2cai:check_hr_existence_on_osm')
            ->dailyAt('06:30')
            ->description('Check hiking routes existence on OSM');

        // osm2cai:set-hr-osm2cai-status-4
        $schedule->exec('docker exec -it php81_osm2cai2 php artisan osm2cai:set-hr-osm2cai-status-4')
            ->dailyAt('07:00')
            ->appendOutputTo('/var/log/laravel-cron.log')
            ->description('Update hiking routes status');

        // osm2cai:cache-mitur-abruzzo-api
        $schedule->exec('docker exec -it php81_osm2cai2 php artisan osm2cai:cache-mitur-abruzzo-api --all')
            ->weeklyOn(6, '09:00') // 6 = Saturday
            ->description('Cache Mitur Abruzzo API (Saturday)');
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
