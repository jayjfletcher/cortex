<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Resilience\Exceptions\BulkheadRejectedException;
use JayI\Cortex\Plugins\Resilience\Strategies\BulkheadStrategy;

describe('BulkheadStrategy', function () {
    test('executes operation successfully', function () {
        $strategy = new BulkheadStrategy(maxConcurrent: 5);

        $result = $strategy->execute(fn () => 'success');

        expect($result)->toBe('success');
    });

    test('tracks active count during execution', function () {
        $strategy = new BulkheadStrategy(maxConcurrent: 5);
        $activeCountDuringExecution = 0;

        $strategy->execute(function () use ($strategy, &$activeCountDuringExecution) {
            $activeCountDuringExecution = $strategy->getActiveCount();

            return 'done';
        });

        expect($activeCountDuringExecution)->toBe(1);
        expect($strategy->getActiveCount())->toBe(0);
    });

    test('decrements active count after exception', function () {
        $strategy = new BulkheadStrategy(maxConcurrent: 5);

        try {
            $strategy->execute(function () {
                throw new RuntimeException('Fail');
            });
        } catch (RuntimeException) {
        }

        expect($strategy->getActiveCount())->toBe(0);
    });

    test('rejects when at capacity with full queue', function () {
        $strategy = new BulkheadStrategy(maxConcurrent: 1, maxQueue: 0);

        // Start one "active" operation by manipulating internal state
        $reflection = new ReflectionClass($strategy);
        $activeCountProperty = $reflection->getProperty('activeCount');
        $activeCountProperty->setValue($strategy, 1);

        expect(fn () => $strategy->execute(fn () => 'should not run'))->toThrow(BulkheadRejectedException::class);
    });

    test('returns correct available slots', function () {
        $strategy = new BulkheadStrategy(maxConcurrent: 10);

        expect($strategy->getAvailableSlots())->toBe(10);

        // Simulate active operations
        $reflection = new ReflectionClass($strategy);
        $activeCountProperty = $reflection->getProperty('activeCount');
        $activeCountProperty->setValue($strategy, 7);

        expect($strategy->getAvailableSlots())->toBe(3);
    });

    test('reports at capacity correctly', function () {
        $strategy = new BulkheadStrategy(maxConcurrent: 5);

        expect($strategy->isAtCapacity())->toBeFalse();

        // Simulate reaching capacity
        $reflection = new ReflectionClass($strategy);
        $activeCountProperty = $reflection->getProperty('activeCount');
        $activeCountProperty->setValue($strategy, 5);

        expect($strategy->isAtCapacity())->toBeTrue();
    });

    test('resets state correctly', function () {
        $strategy = new BulkheadStrategy(maxConcurrent: 5);

        // Simulate some state
        $reflection = new ReflectionClass($strategy);
        $activeCountProperty = $reflection->getProperty('activeCount');
        $activeCountProperty->setValue($strategy, 3);
        $queuedCountProperty = $reflection->getProperty('queuedCount');
        $queuedCountProperty->setValue($strategy, 2);

        $strategy->reset();

        expect($strategy->getActiveCount())->toBe(0);
        expect($strategy->getQueuedCount())->toBe(0);
    });

    test('returns queued count', function () {
        $strategy = new BulkheadStrategy(maxConcurrent: 5);

        expect($strategy->getQueuedCount())->toBe(0);

        // Simulate queued operations
        $reflection = new ReflectionClass($strategy);
        $queuedCountProperty = $reflection->getProperty('queuedCount');
        $queuedCountProperty->setValue($strategy, 3);

        expect($strategy->getQueuedCount())->toBe(3);
    });

    test('available slots never negative', function () {
        $strategy = new BulkheadStrategy(maxConcurrent: 5);

        // Simulate more active than max (shouldn't happen in practice)
        $reflection = new ReflectionClass($strategy);
        $activeCountProperty = $reflection->getProperty('activeCount');
        $activeCountProperty->setValue($strategy, 10);

        expect($strategy->getAvailableSlots())->toBe(0);
    });
});

describe('BulkheadRejectedException', function () {
    test('contains active and queued counts', function () {
        $exception = new BulkheadRejectedException('Test message', 5, 10);

        expect($exception->getMessage())->toBe('Test message');
        expect($exception->activeCount)->toBe(5);
        expect($exception->queuedCount)->toBe(10);
    });

    test('has default values', function () {
        $exception = new BulkheadRejectedException;

        expect($exception->getMessage())->toBe('Bulkhead rejected request');
        expect($exception->activeCount)->toBe(0);
        expect($exception->queuedCount)->toBe(0);
    });
});
