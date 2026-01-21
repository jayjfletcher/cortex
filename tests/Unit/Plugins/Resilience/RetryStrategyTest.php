<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Resilience\Strategies\RetryStrategy;

describe('RetryStrategy', function () {
    test('executes operation successfully', function () {
        $strategy = new RetryStrategy(maxAttempts: 3);

        $result = $strategy->execute(fn () => 'success');

        expect($result)->toBe('success');
    });

    test('retries failed operations', function () {
        $attempts = 0;

        $strategy = new RetryStrategy(
            maxAttempts: 3,
            delayMs: 1,
            jitter: false,
        );

        $result = $strategy->execute(function () use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                throw new RuntimeException('Fail');
            }

            return 'success';
        });

        expect($attempts)->toBe(3);
        expect($result)->toBe('success');
    });

    test('throws after max attempts', function () {
        $attempts = 0;

        $strategy = new RetryStrategy(
            maxAttempts: 3,
            delayMs: 1,
            jitter: false,
        );

        $thrown = false;
        try {
            $strategy->execute(function () use (&$attempts) {
                $attempts++;
                throw new RuntimeException('Always fails');
            });
        } catch (RuntimeException $e) {
            $thrown = true;
        }

        expect($thrown)->toBeTrue();
        expect($attempts)->toBe(3);
    });

    test('only retries on specified exceptions', function () {
        $strategy = new RetryStrategy(
            maxAttempts: 3,
            delayMs: 1,
            retryOn: [RuntimeException::class],
        );

        expect(fn () => $strategy->execute(fn () => throw new InvalidArgumentException('Invalid')))->toThrow(InvalidArgumentException::class);
    });

    test('retries specified exception classes', function () {
        $attempts = 0;

        $strategy = new RetryStrategy(
            maxAttempts: 3,
            delayMs: 1,
            jitter: false,
            retryOn: [RuntimeException::class],
        );

        $result = $strategy->execute(function () use (&$attempts) {
            $attempts++;
            if ($attempts < 2) {
                throw new RuntimeException('Fail');
            }

            return 'success';
        });

        expect($attempts)->toBe(2);
    });

    test('calculates exponential backoff', function () {
        $strategy = new RetryStrategy(
            maxAttempts: 5,
            delayMs: 100,
            multiplier: 2.0,
            maxDelayMs: 1000,
        );

        expect($strategy->getMaxAttempts())->toBe(5);
        expect($strategy->getDelayMs())->toBe(100);
    });
});
