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
     * Generate the cache key for the current model
     *
     * @return string The cache key path
     */
    protected function getCacheKey(): string
    {
        $modelName = strtolower(class_basename($this));

        return "{$modelName}/{$this->id}.json";
    }

    /**
     * Cache the provided data
     *
     * @param  mixed  $data  The data to cache
     * @param  string  $disk  The disk name
     */
    public function cacheDataToAws($data, string $disk): void
    {
        $key = $this->getCacheKey();
        try {
            Storage::disk($disk)->put($key, json_encode($data));
        } catch (\Exception $e) {
            Log::error("Error caching data for key {$key}: ".$e->getMessage());
        }
    }

    /**
     * Retrieve cached data
     *
     * @param  string  $disk  The disk name
     * @return array|null The cached data as array or null if not found
     */
    public function getCachedData(string $disk): ?array
    {
        $key = $this->getCacheKey();
        if (Storage::disk($disk)->exists($key)) {
            return json_decode(Storage::disk($disk)->get($key), true);
        }

        return ['message' => class_basename($this).' not found'];
    }

    /**
     * Get the public URL for the cached data
     *
     * @param  string  $disk  The disk name
     * @return string The public URL
     */
    public function getPublicAwsUrl(string $disk): string
    {
        $key = $this->getCacheKey();
        $awsUrl = config('filesystems.disks.'.$disk)['url'];

        return $awsUrl.'/'.config('filesystems.disks.'.$disk)['root'].$key;
    }

    /**
     * Delete cached data
     *
     * @param  string  $disk  The disk name
     */
    public function deleteCache(string $disk): void
    {
        $key = $this->getCacheKey();
        Storage::disk($disk)->delete($key);
    }
}
