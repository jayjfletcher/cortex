<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Cache\Contracts;

use JayI\Cortex\Plugins\Cache\Data\CacheEntry;

interface CacheStrategyContract
{
    /**
     * Get the strategy identifier.
     */
    public function id(): string;

    /**
     * Generate a cache key for the request.
     *
     * @param  array<string, mixed>  $request
     */
    public function generateKey(array $request): string;

    /**
     * Check if a cached response is valid for a request.
     *
     * @param  array<string, mixed>  $request
     */
    public function isValid(CacheEntry $entry, array $request): bool;

    /**
     * Get the cache from storage.
     *
     * @param  array<string, mixed>  $request
     */
    public function get(array $request): ?CacheEntry;

    /**
     * Store a response in cache.
     *
     * @param  array<string, mixed>  $request
     */
    public function put(array $request, mixed $response, ?int $ttlSeconds = null): CacheEntry;

    /**
     * Remove a cached entry.
     */
    public function forget(string $key): void;

    /**
     * Clear all cached entries.
     */
    public function flush(): void;
}
