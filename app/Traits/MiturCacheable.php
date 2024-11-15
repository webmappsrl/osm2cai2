<?php

namespace App\Traits;

use App\Traits\Cacheable;

/**
 * Trait for handling MITUR data caching functionality
 * 
 * This trait extends the base Cacheable trait to provide specific caching functionality
 * for MITUR data using a dedicated storage disk.
 */
trait MiturCacheable
{
    use Cacheable;

    /**
     * Get the storage disk name for MITUR data caching
     * 
     * @return string The MITUR disk name
     */
    protected function getDisk(): string
    {
        return 'wmfemitur';
    }

    /**
     * Get the base path for MITUR cached files
     * Uses root directory defined in filesystems.php by default
     * 
     * @return string Empty string for root path
     */
    protected function getBasePath(): string
    {
        return '';
    }

    /**
     * Cache the provided MITUR data
     * 
     * @param mixed $data The MITUR data to cache
     * @return void
     */
    public function cacheMiturData($data): void
    {
        $this->cacheData($data);
    }

    /**
     * Retrieve cached MITUR data
     * 
     * @return array|null The cached MITUR data as array or null if not found
     */
    public function getMiturCachedData(): ?array
    {
        return $this->getCachedData();
    }

    /**
     * Delete cached MITUR data
     * 
     * @return void
     */
    public function deleteMiturCache(): void
    {
        $this->deleteCache();
    }
}
