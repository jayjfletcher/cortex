<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Resilience\Contracts;

use Closure;

interface ResiliencePolicyContract extends ResilienceStrategyContract
{
    /**
     * Add a strategy to the policy.
     */
    public function withStrategy(ResilienceStrategyContract $strategy): self;

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
    ): self;

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
    ): self;

    /**
     * Add timeout strategy.
     */
    public function withTimeout(int $timeoutSeconds = 30): self;

    /**
     * Add fallback strategy.
     *
     * @param  Closure(\Throwable): mixed  $fallback
     * @param  array<int, class-string<\Throwable>>  $handleOn
     */
    public function withFallback(Closure $fallback, array $handleOn = []): self;

    /**
     * Add rate limiter strategy.
     */
    public function withRateLimiter(
        int $maxTokens = 10,
        float $refillRate = 1.0,
        bool $waitForToken = false,
        int $maxWaitSeconds = 60,
    ): self;

    /**
     * Add bulkhead strategy.
     */
    public function withBulkhead(
        int $maxConcurrent = 10,
        int $maxQueue = 100,
    ): self;

    /**
     * Get all strategies in the policy.
     *
     * @return array<int, ResilienceStrategyContract>
     */
    public function getStrategies(): array;
}
