<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Cache\Strategies;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use JayI\Cortex\Plugins\Cache\Contracts\CacheStrategyContract;
use JayI\Cortex\Plugins\Cache\Data\CacheEntry;

/**
 * Cache strategy using exact request matching.
 */
class ExactMatchStrategy implements CacheStrategyContract
{
    protected string $prefix = 'cortex_cache_';

    public function __construct(
        protected CacheRepository $cache,
        protected int $defaultTtl = 3600,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return 'exact-match';
    }

    /**
     * {@inheritdoc}
     */
    public function generateKey(array $request): string
    {
        // Normalize request for consistent hashing
        $normalized = $this->normalizeRequest($request);

        return $this->prefix.hash('sha256', json_encode($normalized));
    }

    /**
     * {@inheritdoc}
     */
    public function isValid(CacheEntry $entry, array $request): bool
    {
        return ! $entry->isExpired();
    }

    /**
     * {@inheritdoc}
     */
    public function get(array $request): ?CacheEntry
    {
        $key = $this->generateKey($request);
        $entry = $this->cache->get($key);

        if ($entry === null) {
            return null;
        }

        if (! $entry instanceof CacheEntry) {
            return null;
        }

        if (! $this->isValid($entry, $request)) {
            $this->forget($key);

            return null;
        }

        // Record hit and update cache
        $updatedEntry = $entry->recordHit();
        $this->cache->put($key, $updatedEntry, $entry->expiresAt);

        return $updatedEntry;
    }

    /**
     * {@inheritdoc}
     */
    public function put(array $request, mixed $response, ?int $ttlSeconds = null): CacheEntry
    {
        $key = $this->generateKey($request);
        $ttl = $ttlSeconds ?? $this->defaultTtl;

        $entry = CacheEntry::create(
            key: $key,
            response: $response,
            ttlSeconds: $ttl,
            metadata: ['strategy' => $this->id()],
        );

        $this->cache->put($key, $entry, $ttl);

        return $entry;
    }

    /**
     * {@inheritdoc}
     */
    public function forget(string $key): void
    {
        $this->cache->forget($key);
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): void
    {
        // Note: This only works with tagged cache stores
        // For non-tagged stores, implement your own flushing mechanism
        $this->cache->flush();
    }

    /**
     * Normalize a request for consistent cache key generation.
     *
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    protected function normalizeRequest(array $request): array
    {
        // Remove fields that shouldn't affect cache key
        $excludeFields = ['timestamp', 'request_id', 'trace_id'];

        $normalized = array_diff_key($request, array_flip($excludeFields));

        // Sort arrays recursively for consistent ordering
        $this->sortRecursive($normalized);

        return $normalized;
    }

    /**
     * Recursively sort an array by keys.
     *
     * @param  array<string, mixed>  $array
     */
    protected function sortRecursive(array &$array): void
    {
        ksort($array);

        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->sortRecursive($value);
            }
        }
    }

    /**
     * Set the cache key prefix.
     */
    public function setPrefix(string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }
}
