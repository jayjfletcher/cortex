<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Cache;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Container\Container;
use JayI\Cortex\Contracts\PluginContract;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Plugins\Cache\Contracts\CacheStrategyContract;
use JayI\Cortex\Plugins\Cache\Contracts\ResponseCacheContract;
use JayI\Cortex\Plugins\Cache\Strategies\ExactMatchStrategy;
use JayI\Cortex\Plugins\Cache\Strategies\SemanticCacheStrategy;

class CachePlugin implements PluginContract
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected Container $container,
        protected array $config = [],
    ) {}

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return 'cache';
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'Cache';
    }

    /**
     * {@inheritdoc}
     */
    public function version(): string
    {
        return '1.0.0';
    }

    /**
     * {@inheritdoc}
     */
    public function dependencies(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function provides(): array
    {
        return ['cache'];
    }

    /**
     * {@inheritdoc}
     */
    public function register(PluginManagerContract $manager): void
    {
        // Register cache strategy
        $this->container->singleton(CacheStrategyContract::class, function () {
            return $this->createStrategy();
        });

        // Register response cache
        $this->container->singleton(ResponseCacheContract::class, function () {
            return new ResponseCache(
                $this->container->make(CacheStrategyContract::class)
            );
        });

        $this->container->bind(ResponseCache::class, function () {
            return $this->container->make(ResponseCacheContract::class);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function boot(PluginManagerContract $manager): void
    {
        // No boot actions needed
    }

    /**
     * Create the cache strategy based on configuration.
     */
    protected function createStrategy(): CacheStrategyContract
    {
        $cache = $this->container->make(CacheRepository::class);
        $strategyType = $this->config['strategy'] ?? 'exact';
        $ttl = $this->config['ttl'] ?? 3600;

        return match ($strategyType) {
            'semantic' => $this->createSemanticStrategy($cache, $ttl),
            default => $this->createExactMatchStrategy($cache, $ttl),
        };
    }

    /**
     * Create an exact match strategy.
     */
    protected function createExactMatchStrategy(CacheRepository $cache, int $ttl): ExactMatchStrategy
    {
        $strategy = new ExactMatchStrategy($cache, $ttl);

        if (isset($this->config['prefix'])) {
            $strategy->setPrefix($this->config['prefix']);
        }

        return $strategy;
    }

    /**
     * Create a semantic cache strategy.
     */
    protected function createSemanticStrategy(CacheRepository $cache, int $ttl): SemanticCacheStrategy
    {
        $threshold = $this->config['semantic_threshold'] ?? 0.95;

        return new SemanticCacheStrategy($cache, $ttl, $threshold);
    }
}
