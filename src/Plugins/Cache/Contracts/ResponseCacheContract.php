<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Cache\Contracts;

interface ResponseCacheContract
{
    /**
     * Get a cached response for a request.
     *
     * @param  array<string, mixed>  $request
     */
    public function get(array $request): mixed;

    /**
     * Cache a response.
     *
     * @param  array<string, mixed>  $request
     */
    public function put(array $request, mixed $response, ?int $ttlSeconds = null): void;

    /**
     * Check if a cached response exists.
     *
     * @param  array<string, mixed>  $request
     */
    public function has(array $request): bool;

    /**
     * Forget a cached response.
     *
     * @param  array<string, mixed>  $request
     */
    public function forget(array $request): void;

    /**
     * Clear all cached responses.
     */
    public function flush(): void;

    /**
     * Get cache statistics.
     *
     * @return array{hits: int, misses: int, size: int}
     */
    public function stats(): array;
}
