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

        // MODIFICATO: da '02:00' a '07:00' CET
        $schedule->command('wm-osmfeatures:sync')
            ->dailyAt('07:00')
            ->description('Sync wm-osmfeatures');

        // MODIFICATO: da '03:30' a '08:00' CET
        $schedule->command('wm-osmfeatures:import-sync')
            ->dailyAt('08:00')
            ->description('Import-sync wm-osmfeatures');

        $schedule->command('horizon:snapshot')
            ->hourlyAt(10)
            ->description('Take Horizon snapshot');

        // VERIFICARE: questo parte alle 05:00 CET quando osmfeatures 
        // potrebbe ancora essere in import (finisce 05:00-06:00 CET)
        // Potrebbe essere necessario spostarlo dopo le 08:00 CET
        $schedule->command('osm2cai:update-hiking-routes')
            ->dailyAt('05:00')
            ->description('Check osmfeatures for hiking routes updates');

        $schedule->command('osm2cai:calculate-region-hiking-routes-intersection')
            ->dailyAt('7:00')
            ->description('Calculate region hiking routes intersection');

        $schedule->command('osm2cai:check-hr-existence-on-osm')
            ->dailyAt('06:30')
            ->description('Check hiking routes existence on OSM');

        $schedule->command('osm2cai:fix-osmfeatures-sda')
            ->dailyAt('07:00')
            ->description('Update hiking routes status');
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
