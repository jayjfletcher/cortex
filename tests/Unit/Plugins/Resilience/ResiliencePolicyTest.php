<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Resilience\Exceptions\CircuitOpenException;
use JayI\Cortex\Plugins\Resilience\ResiliencePolicy;
use JayI\Cortex\Plugins\Resilience\Strategies\CircuitBreakerStrategy;
use JayI\Cortex\Plugins\Resilience\Strategies\FallbackStrategy;
use JayI\Cortex\Plugins\Resilience\Strategies\RetryStrategy;

describe('ResiliencePolicy', function () {
    test('creates empty policy', function () {
        $policy = ResiliencePolicy::make();

        expect($policy->hasStrategies())->toBeFalse();
    });

    test('executes operation without strategies', function () {
        $policy = ResiliencePolicy::make();

        $result = $policy->execute(fn () => 'success');

        expect($result)->toBe('success');
    });

    test('chains multiple strategies', function () {
        $attempts = 0;

        // Strategies added: [fallback, retry]
        // Reversed for wrapping: [retry, fallback]
        // Final: fallback(retry(operation))
        // retry tries 2 times, throws, fallback catches
        $policy = ResiliencePolicy::make()
            ->withFallback(fn () => 'fallback')
            ->withRetry(maxAttempts: 2, delayMs: 1, jitter: false);

        $result = $policy->execute(function () use (&$attempts) {
            $attempts++;
            throw new RuntimeException('Fail');
        });

        expect($result)->toBe('fallback');
        expect($attempts)->toBe(2);
    });

    test('adds retry strategy', function () {
        $policy = ResiliencePolicy::make()
            ->withRetry(maxAttempts: 5);

        $strategy = $policy->getStrategy(RetryStrategy::class);

        expect($strategy)->toBeInstanceOf(RetryStrategy::class);
        expect($strategy->getMaxAttempts())->toBe(5);
    });

    test('adds circuit breaker', function () {
        $policy = ResiliencePolicy::make()
            ->withCircuitBreaker(failureThreshold: 3);

        $strategy = $policy->getStrategy(CircuitBreakerStrategy::class);

        expect($strategy)->toBeInstanceOf(CircuitBreakerStrategy::class);
    });

    test('adds fallback with closure', function () {
        $policy = ResiliencePolicy::make()
            ->withFallback(fn ($e) => "Error: {$e->getMessage()}");

        $result = $policy->execute(fn () => throw new RuntimeException('Failed'));

        expect($result)->toBe('Error: Failed');
    });

    test('adds rate limiter', function () {
        $policy = ResiliencePolicy::make()
            ->withRateLimiter(maxTokens: 5, refillRate: 1.0);

        expect($policy->getStrategies())->toHaveCount(1);
    });

    test('adds bulkhead', function () {
        $policy = ResiliencePolicy::make()
            ->withBulkhead(maxConcurrent: 5);

        expect($policy->getStrategies())->toHaveCount(1);
    });

    test('retrieves specific strategy', function () {
        $policy = ResiliencePolicy::make()
            ->withRetry()
            ->withCircuitBreaker()
            ->withFallbackValue(null);

        expect($policy->getStrategy(RetryStrategy::class))->toBeInstanceOf(RetryStrategy::class);
        expect($policy->getStrategy(CircuitBreakerStrategy::class))->toBeInstanceOf(CircuitBreakerStrategy::class);
        expect($policy->getStrategy(FallbackStrategy::class))->toBeInstanceOf(FallbackStrategy::class);
    });

    test('returns null for missing strategy', function () {
        $policy = ResiliencePolicy::make()->withRetry();

        expect($policy->getStrategy(CircuitBreakerStrategy::class))->toBeNull();
    });
});
