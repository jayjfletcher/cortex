<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Resilience;

use Illuminate\Contracts\Container\Container;
use JayI\Cortex\Contracts\PluginContract;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Plugins\Resilience\Contracts\ResiliencePolicyContract;
use JayI\Cortex\Plugins\Resilience\Contracts\ResilienceStrategyContract;

class ResiliencePlugin implements PluginContract
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
        return 'resilience';
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'Resilience';
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
        return ['resilience'];
    }

    /**
     * {@inheritdoc}
     */
    public function register(PluginManagerContract $manager): void
    {
        // Bind strategy contract to default implementation
        $this->container->bind(ResilienceStrategyContract::class, function () {
            return $this->createDefaultPolicy();
        });

        // Bind policy contract
        $this->container->bind(ResiliencePolicyContract::class, function () {
            return $this->createDefaultPolicy();
        });

        // Bind ResiliencePolicy for direct resolution
        $this->container->bind(ResiliencePolicy::class, function () {
            return $this->createDefaultPolicy();
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
     * Create the default resilience policy from config.
     */
    protected function createDefaultPolicy(): ResiliencePolicy
    {
        $policy = ResiliencePolicy::make();

        // Apply retry strategy if configured
        if ($this->config['retry']['enabled'] ?? false) {
            $policy->withRetry(
                maxAttempts: $this->config['retry']['max_attempts'] ?? 3,
                delayMs: $this->config['retry']['delay_ms'] ?? 1000,
                multiplier: $this->config['retry']['multiplier'] ?? 2.0,
                maxDelayMs: $this->config['retry']['max_delay_ms'] ?? 30000,
                jitter: $this->config['retry']['jitter'] ?? true,
            );
        }

        // Apply circuit breaker if configured
        if ($this->config['circuit_breaker']['enabled'] ?? false) {
            $policy->withCircuitBreaker(
                failureThreshold: $this->config['circuit_breaker']['failure_threshold'] ?? 5,
                successThreshold: $this->config['circuit_breaker']['success_threshold'] ?? 3,
                resetTimeoutSeconds: $this->config['circuit_breaker']['reset_timeout_seconds'] ?? 60,
            );
        }

        // Apply timeout if configured
        if ($this->config['timeout']['enabled'] ?? false) {
            $policy->withTimeout(
                timeoutSeconds: $this->config['timeout']['seconds'] ?? 30
            );
        }

        // Apply rate limiter if configured
        if ($this->config['rate_limiter']['enabled'] ?? false) {
            $policy->withRateLimiter(
                maxTokens: $this->config['rate_limiter']['max_tokens'] ?? 10,
                refillRate: $this->config['rate_limiter']['refill_rate'] ?? 1.0,
                waitForToken: $this->config['rate_limiter']['wait_for_token'] ?? false,
            );
        }

        // Apply bulkhead if configured
        if ($this->config['bulkhead']['enabled'] ?? false) {
            $policy->withBulkhead(
                maxConcurrent: $this->config['bulkhead']['max_concurrent'] ?? 10,
                maxQueue: $this->config['bulkhead']['max_queue'] ?? 100,
            );
        }

        return $policy;
    }
}
