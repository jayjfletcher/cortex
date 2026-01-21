<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Resilience\CircuitState;
use JayI\Cortex\Plugins\Resilience\Exceptions\CircuitOpenException;
use JayI\Cortex\Plugins\Resilience\Strategies\CircuitBreakerStrategy;

describe('CircuitBreakerStrategy', function () {
    test('starts in closed state', function () {
        $breaker = new CircuitBreakerStrategy;

        expect($breaker->getState())->toBe(CircuitState::Closed);
    });

    test('allows requests in closed state', function () {
        $breaker = new CircuitBreakerStrategy;

        $result = $breaker->execute(fn () => 'success');

        expect($result)->toBe('success');
    });

    test('opens after failure threshold', function () {
        $breaker = new CircuitBreakerStrategy(failureThreshold: 3);

        for ($i = 0; $i < 3; $i++) {
            try {
                $breaker->execute(fn () => throw new RuntimeException('Fail'));
            } catch (RuntimeException) {
            }
        }

        expect($breaker->getState())->toBe(CircuitState::Open);
    });

    test('rejects requests in open state', function () {
        $breaker = new CircuitBreakerStrategy(failureThreshold: 1);

        try {
            $breaker->execute(fn () => throw new RuntimeException('Fail'));
        } catch (RuntimeException) {
        }

        expect(fn () => $breaker->execute(fn () => 'should not execute'))->toThrow(CircuitOpenException::class);
    });

    test('resets on successful operations in closed state', function () {
        $breaker = new CircuitBreakerStrategy(failureThreshold: 3);

        // Two failures
        for ($i = 0; $i < 2; $i++) {
            try {
                $breaker->execute(fn () => throw new RuntimeException('Fail'));
            } catch (RuntimeException) {
            }
        }

        expect($breaker->getFailureCount())->toBe(2);

        // Success resets count
        $breaker->execute(fn () => 'success');

        expect($breaker->getFailureCount())->toBe(0);
    });

    test('can be manually reset', function () {
        $breaker = new CircuitBreakerStrategy(failureThreshold: 1);

        try {
            $breaker->execute(fn () => throw new RuntimeException('Fail'));
        } catch (RuntimeException) {
        }

        expect($breaker->getState())->toBe(CircuitState::Open);

        $breaker->reset();

        expect($breaker->getState())->toBe(CircuitState::Closed);
    });

    test('only trips on specified exceptions', function () {
        $breaker = new CircuitBreakerStrategy(
            failureThreshold: 1,
            tripOn: [RuntimeException::class],
        );

        try {
            $breaker->execute(fn () => throw new InvalidArgumentException('Invalid'));
        } catch (InvalidArgumentException) {
        }

        // Should still be closed because InvalidArgumentException not in tripOn
        expect($breaker->getState())->toBe(CircuitState::Closed);
    });

    test('tracks remaining cooldown', function () {
        $breaker = new CircuitBreakerStrategy(
            failureThreshold: 1,
            resetTimeoutSeconds: 60,
        );

        try {
            $breaker->execute(fn () => throw new RuntimeException('Fail'));
        } catch (RuntimeException) {
        }

        $remaining = $breaker->getRemainingCooldown();
        expect($remaining)->toBeGreaterThan(0);
        expect($remaining)->toBeLessThanOrEqual(60);
    });
});
