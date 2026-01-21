<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Resilience\Exceptions\RateLimitExceededException;
use JayI\Cortex\Plugins\Resilience\Strategies\RateLimiterStrategy;

describe('RateLimiterStrategy', function () {
    test('executes operation when tokens available', function () {
        $strategy = new RateLimiterStrategy(maxTokens: 10);

        $result = $strategy->execute(fn () => 'success');

        expect($result)->toBe('success');
    });

    test('consumes tokens on each execution', function () {
        $strategy = new RateLimiterStrategy(maxTokens: 5, refillRate: 0.0001);

        for ($i = 0; $i < 5; $i++) {
            $strategy->execute(fn () => 'ok');
        }

        expect(fn () => $strategy->execute(fn () => 'fail'))->toThrow(RateLimitExceededException::class);
    });

    test('throws rate limit exception when exhausted', function () {
        $strategy = new RateLimiterStrategy(maxTokens: 1, refillRate: 0.0001);

        $strategy->execute(fn () => 'first');

        expect(fn () => $strategy->execute(fn () => 'second'))->toThrow(RateLimitExceededException::class);
    });

    test('returns time until next token', function () {
        $strategy = new RateLimiterStrategy(maxTokens: 1, refillRate: 1.0);

        // Use all tokens
        $strategy->execute(fn () => 'consume');

        $timeUntilNext = $strategy->getTimeUntilNextToken();

        expect($timeUntilNext)->toBeGreaterThan(0.0);
        expect($timeUntilNext)->toBeLessThanOrEqual(1.0);
    });

    test('returns zero time when tokens available', function () {
        $strategy = new RateLimiterStrategy(maxTokens: 10);

        $timeUntilNext = $strategy->getTimeUntilNextToken();

        expect($timeUntilNext)->toBe(0.0);
    });

    test('refills tokens over time', function () {
        $strategy = new RateLimiterStrategy(maxTokens: 10, refillRate: 10000.0);

        // Consume all tokens
        for ($i = 0; $i < 10; $i++) {
            $strategy->execute(fn () => 'ok');
        }

        // Wait a tiny bit for refill
        usleep(1000);

        // Should have refilled some tokens
        $available = $strategy->getAvailableTokens();
        expect($available)->toBeGreaterThan(0);
    });

    test('does not exceed max tokens on refill', function () {
        $strategy = new RateLimiterStrategy(maxTokens: 5, refillRate: 10000.0);

        // Wait for potential over-refill
        usleep(10000);

        $available = $strategy->getAvailableTokens();
        expect($available)->toBeLessThanOrEqual(5.0);
    });

    test('resets to full capacity', function () {
        $strategy = new RateLimiterStrategy(maxTokens: 10, refillRate: 0.0001);

        // Consume all tokens
        for ($i = 0; $i < 10; $i++) {
            $strategy->execute(fn () => 'ok');
        }

        expect($strategy->getAvailableTokens())->toBeLessThan(1);

        $strategy->reset();

        expect($strategy->getAvailableTokens())->toBe(10.0);
    });

    test('waits for token when configured', function () {
        $strategy = new RateLimiterStrategy(
            maxTokens: 1,
            refillRate: 1000.0, // Fast refill for testing
            waitForToken: true,
            maxWaitSeconds: 1,
        );

        // Consume the token
        $strategy->execute(fn () => 'first');

        // Should wait and succeed
        $result = $strategy->execute(fn () => 'second');
        expect($result)->toBe('second');
    });

    test('exception contains retry after value', function () {
        $strategy = new RateLimiterStrategy(maxTokens: 1, refillRate: 1.0);

        $strategy->execute(fn () => 'consume');

        try {
            $strategy->execute(fn () => 'fail');
            $this->fail('Expected exception');
        } catch (RateLimitExceededException $e) {
            expect($e->retryAfterSeconds)->toBeGreaterThan(0);
            expect($e->retryAfterSeconds)->toBeLessThanOrEqual(1.0);
        }
    });
});

describe('RateLimitExceededException', function () {
    test('contains retry after seconds', function () {
        $exception = new RateLimitExceededException('Rate limit hit', 5.5);

        expect($exception->getMessage())->toBe('Rate limit hit');
        expect($exception->retryAfterSeconds)->toBe(5.5);
    });

    test('has default values', function () {
        $exception = new RateLimitExceededException;

        expect($exception->getMessage())->toBe('Rate limit exceeded');
        expect($exception->retryAfterSeconds)->toBe(0.0);
    });
});
