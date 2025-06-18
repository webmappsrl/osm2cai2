<?php

namespace App\Console\Commands;

use App\Models\Region;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RefreshDashboardCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'osm2cai:refresh-dashboard-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all dashboard cache to force fresh data loading';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('Starting dashboard cache clearing...');

        $this->clearDashboardCache();

        $this->info('Dashboard cache cleared successfully! Fresh data will be loaded on next access.');

        return Command::SUCCESS;
    }

    /**
     * Clear all dashboard-related cache entries
     */
    private function clearDashboardCache(): void
    {
        $this->info('Clearing old cache...');

        $cacheKeys = [
            // National and Italy dashboard
            'national_sal',
            'italy_dashboard_data',
            'national_data',

            // Statistics and metrics
            'percorsi-favoriti-dashboard-data',
            'ec-pois-count',
            'ugc_poi_water_count',
            'usersByRole',
            'usersByRegion',
            'mostActiveUsers',

            // SAL Mitur
            'sal_mitur_regions',
            'sal_mitur_totals',

            // Sectors dashboard
            'sectors_dashboard_ids',
            'sectors_dashboard_items',
            'sectors_dashboard_data',

            // Tables
            'regions_table_data',
        ];

        // Clear static keys
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        // Clear dynamic keys
        $this->clearDynamicCacheKeys();
    }

    /**
     * Clear dynamic cache keys with patterns
     */
    private function clearDynamicCacheKeys(): void
    {
        // Clear regional data for all regions
        $regionIds = Region::pluck('id');
        foreach ($regionIds as $regionId) {
            Cache::forget("regional_data_{$regionId}");
            Cache::forget("sal_issue_status_{$regionId}");
        }

        // Clear user-specific cache for active users
        $userIds = User::pluck('id');
        foreach ($userIds as $userId) {
            foreach (['Province', 'Area', 'Sector'] as $model) {
                Cache::forget("local_cards_data_{$userId}_App\\Models\\{$model}");
            }
        }

        // Clear hiking routes cache for different statuses
        foreach ([1, 2, 3, 4, '3_4', '34'] as $status) {
            Cache::forget("hikingRoutesSda{$status}");
            Cache::forget("total_km_{$status}");
        }

        // Clear children stats cache
        foreach (['regions', 'provinces', 'areas', 'sectors'] as $table) {
            Cache::forget("children_stats_{$table}");
        }
    }
}
