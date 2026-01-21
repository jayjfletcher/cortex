<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Resilience;

use Closure;
use JayI\Cortex\Plugins\Resilience\Contracts\ResiliencePolicyContract;
use JayI\Cortex\Plugins\Resilience\Contracts\ResilienceStrategyContract;
use JayI\Cortex\Plugins\Resilience\Strategies\BulkheadStrategy;
use JayI\Cortex\Plugins\Resilience\Strategies\CircuitBreakerStrategy;
use JayI\Cortex\Plugins\Resilience\Strategies\FallbackStrategy;
use JayI\Cortex\Plugins\Resilience\Strategies\RateLimiterStrategy;
use JayI\Cortex\Plugins\Resilience\Strategies\RetryStrategy;
use JayI\Cortex\Plugins\Resilience\Strategies\TimeoutStrategy;

/**
 * Compose multiple resilience strategies into a single policy.
 */
class ResiliencePolicy implements ResiliencePolicyContract
{
    /** @var array<int, ResilienceStrategyContract> */
    protected array $strategies = [];

    /**
     * Create a new policy.
     */
    public static function make(): self
    {
        return new self;
    }

    /**
     * Add a strategy to the policy.
     */
    public function withStrategy(ResilienceStrategyContract $strategy): self
    {
        $this->strategies[] = $strategy;

        return $this;
    }

    /**
     * Add retry strategy.
     *
     * @param  array<int, class-string<\Throwable>>  $retryOn
     */
    public function withRetry(
        int $maxAttempts = 3,
        int $delayMs = 1000,
        float $multiplier = 2.0,
        int $maxDelayMs = 30000,
        bool $jitter = true,
        array $retryOn = [],
    ): self {
        return $this->withStrategy(new RetryStrategy(
            $maxAttempts,
            $delayMs,
            $multiplier,
            $maxDelayMs,
            $jitter,
            $retryOn
        ));
    }

    /**
     * Add circuit breaker strategy.
     *
     * @param  array<int, class-string<\Throwable>>  $tripOn
     */
    public function withCircuitBreaker(
        int $failureThreshold = 5,
        int $successThreshold = 3,
        int $resetTimeoutSeconds = 60,
        array $tripOn = [],
    ): self {
        return $this->withStrategy(new CircuitBreakerStrategy(
            $failureThreshold,
            $successThreshold,
            $resetTimeoutSeconds,
            $tripOn
        ));
    }

    /**
     * Add timeout strategy.
     */
    public function withTimeout(int $timeoutSeconds = 30): self
    {
        return $this->withStrategy(new TimeoutStrategy($timeoutSeconds));
    }

    /**
     * Add fallback strategy.
     *
     * @param  Closure(\Throwable): mixed  $fallback
     * @param  array<int, class-string<\Throwable>>  $handleOn
     */
    public function withFallback(Closure $fallback, array $handleOn = []): self
    {
        return $this->withStrategy(new FallbackStrategy($fallback, $handleOn));
    }

    /**
     * Add fallback that returns a static value.
     */
    public function withFallbackValue(mixed $value): self
    {
        return $this->withStrategy(FallbackStrategy::value($value));
    }

    /**
     * Add rate limiter strategy.
     */
    public function withRateLimiter(
        int $maxTokens = 10,
        float $refillRate = 1.0,
        bool $waitForToken = false,
        int $maxWaitSeconds = 60,
    ): self {
        return $this->withStrategy(new RateLimiterStrategy(
            $maxTokens,
            $refillRate,
            $waitForToken,
            $maxWaitSeconds
        ));
    }

    /**
     * Add bulkhead strategy.
     */
    public function withBulkhead(
        int $maxConcurrent = 10,
        int $maxQueue = 100,
    ): self {
        return $this->withStrategy(new BulkheadStrategy(
            $maxConcurrent,
            $maxQueue
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function execute(Closure $operation): mixed
    {
        if (empty($this->strategies)) {
            return $operation();
        }

        // Build a chain of strategies wrapping the operation
        // Strategies are applied in reverse order (innermost first)
        $wrapped = $operation;

        foreach (array_reverse($this->strategies) as $strategy) {
            $current = $wrapped;
            $wrapped = fn () => $strategy->execute($current);
        }

        return $wrapped();
    }

    /**
     * Get all strategies in the policy.
     *
     * @return array<int, ResilienceStrategyContract>
     */
    public function getStrategies(): array
    {
        return $this->strategies;
    }

    /**
     * Check if the policy has any strategies.
     */
    public function hasStrategies(): bool
    {
        return ! empty($this->strategies);
    }

    /**
     * Get a specific strategy by class name.
     *
     * @template T of ResilienceStrategyContract
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    public function getStrategy(string $class): ?ResilienceStrategyContract
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy instanceof $class) {
                return $strategy;
            }
        }

        return null;
    }
}
