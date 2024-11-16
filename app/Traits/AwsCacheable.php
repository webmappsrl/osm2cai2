<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Trait for handling data caching functionality
 * 
 * This trait provides methods to cache, retrieve and delete data using Laravel's Storage facade.
 */
trait AwsCacheable
{
    /**
     * Get the storage disk name to use for caching
     * 
     * @return string The disk name
     */
    abstract protected function getStorageDisk(): string;


    /**
     * Generate the cache key for the current model
     * 
     * @return string The cache key path
     */
    protected function getCacheKey(): string
    {
        return $this->id . '.geojson';
    }

    /**
     * Cache the provided data
     * 
     * @param mixed $data The data to cache
     * @return void
     */
    public function cacheDataToAws($data): void
    {
        $key = $this->getCacheKey();
        try {
            Storage::disk($this->getStorageDisk())->put($key, json_encode($data));
        } catch (\Exception $e) {
            Log::error("Error caching data for key {$key}: " . $e->getMessage());
        }
    }

    /**
     * Retrieve cached data
     * 
     * @return array|null The cached data as array or null if not found
     */
    public function getCachedData(): ?array
    {
        $key = $this->getCacheKey();
        if (Storage::disk($this->getStorageDisk())->exists($key)) {
            return json_decode(Storage::disk($this->getStorageDisk())->get($key), true);
        }
        return null;
    }
    /**
     * Get the public URL for the cached data
     * 
     * @return string The public URL
     */
    public function getPublicAwsUrl(): string
    {
        $key = $this->getCacheKey();
        $awsUrl = config('filesystems.disks.' . $this->getStorageDisk())['url'];
        return $awsUrl . '/' . config('filesystems.disks.' . $this->getStorageDisk())['root'] . $key;
    }

    /**
     * Delete cached data
     * 
     * @return void
     */
    public function deleteCache(): void
    {
        $key = $this->getCacheKey();
        Storage::disk($this->getDisk())->delete($key);
    }
}
