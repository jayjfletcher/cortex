<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Resilience\Exceptions\TimeoutException;
use JayI\Cortex\Plugins\Resilience\Strategies\TimeoutStrategy;

describe('TimeoutStrategy', function () {
    test('executes operation that completes in time', function () {
        $strategy = new TimeoutStrategy(timeoutSeconds: 30);

        $result = $strategy->execute(fn () => 'success');

        expect($result)->toBe('success');
    });

    test('returns timeout seconds', function () {
        $strategy = new TimeoutStrategy(timeoutSeconds: 45);

        expect($strategy->getTimeoutSeconds())->toBe(45);
    });

    test('throws timeout exception for slow operations', function () {
        $strategy = new TimeoutStrategy(timeoutSeconds: 0);

        // Operation that takes longer than timeout (in post-execution check)
        expect(fn () => $strategy->execute(function () {
            usleep(100000); // 100ms

            return 'done';
        }))->toThrow(TimeoutException::class);
    });

    test('allows fast operations', function () {
        $strategy = new TimeoutStrategy(timeoutSeconds: 10);

        $result = $strategy->execute(fn () => 'fast');

        expect($result)->toBe('fast');
    });
});

describe('TimeoutException', function () {
    test('contains timeout seconds', function () {
        $exception = new TimeoutException('Operation took too long', 30);

        expect($exception->getMessage())->toBe('Operation took too long');
        expect($exception->timeoutSeconds)->toBe(30);
    });

    test('has default values', function () {
        $exception = new TimeoutException;

        expect($exception->getMessage())->toBe('Operation timed out');
        expect($exception->timeoutSeconds)->toBe(0);
    });
});
