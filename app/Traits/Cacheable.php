<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Trait for handling data caching functionality
 * 
 * This trait provides methods to cache, retrieve and delete data using Laravel's Storage facade.
 */
trait Cacheable
{
    /**
     * Get the storage disk name to use for caching
     * 
     * @return string The disk name
     */
    abstract protected function getDisk(): string;

    /**
     * Get the base path for cached files
     * 
     * @return string The base path
     */
    abstract protected function getBasePath(): string;

    /**
     * Generate the cache key for the current model
     * 
     * @return string The cache key path
     */
    protected function getCacheKey(): string
    {
        $modelName = strtolower(class_basename($this));
        $id = $this->osmfeatures_id ?? $this->id;
        return trim($this->getBasePath(), '/') . "/{$modelName}s/{$id}.geojson";
    }

    /**
     * Cache the provided data
     * 
     * @param mixed $data The data to cache
     * @return void
     */
    public function cacheData($data): void
    {
        $key = $this->getCacheKey();
        try {
            Storage::disk($this->getDisk())->put($key, json_encode($data));
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
        $aws = config('filesystems.disks.wmfemitur');
        $url = $aws['url'];
        $fullPath = $url . $key;
        $content = file_get_contents($fullPath);
        return json_decode($content, true);
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
