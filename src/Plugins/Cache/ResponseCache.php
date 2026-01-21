<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Cache;

use JayI\Cortex\Plugins\Cache\Contracts\CacheStrategyContract;
use JayI\Cortex\Plugins\Cache\Contracts\ResponseCacheContract;

/**
 * Response cache implementation using configurable strategies.
 */
class ResponseCache implements ResponseCacheContract
{
    protected int $hits = 0;

    protected int $misses = 0;

    public function __construct(
        protected CacheStrategyContract $strategy,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function get(array $request): mixed
    {
        $entry = $this->strategy->get($request);

        if ($entry === null) {
            $this->misses++;

            return null;
        }

        $this->hits++;

        return $entry->response;
    }

    /**
     * {@inheritdoc}
     */
    public function put(array $request, mixed $response, ?int $ttlSeconds = null): void
    {
        $this->strategy->put($request, $response, $ttlSeconds);
    }

    /**
     * {@inheritdoc}
     */
    public function has(array $request): bool
    {
        return $this->strategy->get($request) !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function forget(array $request): void
    {
        $key = $this->strategy->generateKey($request);
        $this->strategy->forget($key);
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): void
    {
        $this->strategy->flush();
        $this->hits = 0;
        $this->misses = 0;
    }

    /**
     * {@inheritdoc}
     */
    public function stats(): array
    {
        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'size' => 0, // Would need cache-specific implementation
        ];
    }

    /**
     * Get the hit rate as a percentage.
     */
    public function hitRate(): float
    {
        $total = $this->hits + $this->misses;

        if ($total === 0) {
            return 0.0;
        }

        return ($this->hits / $total) * 100;
    }

    /**
     * Get the underlying strategy.
     */
    public function getStrategy(): CacheStrategyContract
    {
        return $this->strategy;
    }
}
