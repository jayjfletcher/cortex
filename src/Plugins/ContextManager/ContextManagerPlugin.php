<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\ContextManager;

use Illuminate\Contracts\Container\Container;
use JayI\Cortex\Contracts\PluginContract;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Plugins\ContextManager\Contracts\ContextManagerContract;
use JayI\Cortex\Plugins\ContextManager\Contracts\ContextStrategyContract;
use JayI\Cortex\Plugins\ContextManager\Strategies\ImportanceStrategy;
use JayI\Cortex\Plugins\ContextManager\Strategies\SlidingWindowStrategy;
use JayI\Cortex\Plugins\ContextManager\Strategies\TruncateOldestStrategy;

class ContextManagerPlugin implements PluginContract
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
        return 'context-manager';
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'Context Manager';
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
        return ['context-manager'];
    }

    /**
     * {@inheritdoc}
     */
    public function register(PluginManagerContract $manager): void
    {
        // Register strategy
        $this->container->singleton(ContextStrategyContract::class, function () {
            return $this->createStrategy();
        });

        // Register context manager
        $this->container->singleton(ContextManagerContract::class, function () {
            $manager = new ContextManager(
                $this->container->make(ContextStrategyContract::class)
            );

            if (isset($this->config['auto_reduce_threshold'])) {
                $manager->setAutoReduceThreshold($this->config['auto_reduce_threshold']);
            }

            return $manager;
        });

        $this->container->bind(ContextManager::class, function () {
            return $this->container->make(ContextManagerContract::class);
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
     * Create the context strategy based on configuration.
     */
    protected function createStrategy(): ContextStrategyContract
    {
        $strategyType = $this->config['strategy'] ?? 'truncate-oldest';

        return match ($strategyType) {
            'importance' => $this->createImportanceStrategy(),
            'sliding-window' => $this->createSlidingWindowStrategy(),
            default => $this->createTruncateOldestStrategy(),
        };
    }

    /**
     * Create truncate oldest strategy.
     */
    protected function createTruncateOldestStrategy(): TruncateOldestStrategy
    {
        return new TruncateOldestStrategy(
            preservePinned: $this->config['preserve_pinned'] ?? true,
            keepMinMessages: $this->config['keep_min_messages'] ?? 2,
        );
    }

    /**
     * Create importance-based strategy.
     */
    protected function createImportanceStrategy(): ImportanceStrategy
    {
        return new ImportanceStrategy(
            recencyWeight: $this->config['recency_weight'] ?? 0.3,
            keepMinMessages: $this->config['keep_min_messages'] ?? 2,
        );
    }

    /**
     * Create sliding window strategy.
     */
    protected function createSlidingWindowStrategy(): SlidingWindowStrategy
    {
        return new SlidingWindowStrategy(
            maxMessages: $this->config['max_messages'] ?? 20,
            preservePinned: $this->config['preserve_pinned'] ?? true,
        );
    }
}
