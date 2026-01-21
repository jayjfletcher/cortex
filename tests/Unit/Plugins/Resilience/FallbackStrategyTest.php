<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Resilience\Strategies\FallbackStrategy;

describe('FallbackStrategy', function () {
    test('executes operation successfully without fallback', function () {
        $strategy = new FallbackStrategy(fn () => 'fallback');

        $result = $strategy->execute(fn () => 'success');

        expect($result)->toBe('success');
    });

    test('uses fallback on exception', function () {
        $strategy = new FallbackStrategy(fn ($e) => "Caught: {$e->getMessage()}");

        $result = $strategy->execute(fn () => throw new RuntimeException('Oops'));

        expect($result)->toBe('Caught: Oops');
    });

    test('passes exception to fallback handler', function () {
        $caughtException = null;
        $strategy = new FallbackStrategy(function ($e) use (&$caughtException) {
            $caughtException = $e;

            return 'handled';
        });

        $strategy->execute(fn () => throw new RuntimeException('Test error'));

        expect($caughtException)->toBeInstanceOf(RuntimeException::class);
        expect($caughtException->getMessage())->toBe('Test error');
    });

    test('only handles specified exception types', function () {
        $strategy = new FallbackStrategy(
            fn () => 'fallback',
            handleOn: [RuntimeException::class]
        );

        // Should handle RuntimeException
        $result = $strategy->execute(fn () => throw new RuntimeException('Runtime'));
        expect($result)->toBe('fallback');

        // Should not handle InvalidArgumentException
        expect(fn () => $strategy->execute(fn () => throw new InvalidArgumentException('Invalid')))->toThrow(InvalidArgumentException::class);
    });

    test('handles exception subclasses', function () {
        $strategy = new FallbackStrategy(
            fn () => 'fallback',
            handleOn: [RuntimeException::class]
        );

        // LogicException extends Exception, not RuntimeException
        expect(fn () => $strategy->execute(fn () => throw new LogicException('Logic')))->toThrow(LogicException::class);

        // UnexpectedValueException extends RuntimeException
        $result = $strategy->execute(fn () => throw new UnexpectedValueException('Unexpected'));
        expect($result)->toBe('fallback');
    });

    test('creates static value fallback', function () {
        $strategy = FallbackStrategy::value('default value');

        $result = $strategy->execute(fn () => throw new RuntimeException('Error'));

        expect($result)->toBe('default value');
    });

    test('creates null fallback', function () {
        $strategy = FallbackStrategy::null();

        $result = $strategy->execute(fn () => throw new RuntimeException('Error'));

        expect($result)->toBeNull();
    });

    test('creates empty array fallback', function () {
        $strategy = FallbackStrategy::emptyArray();

        $result = $strategy->execute(fn () => throw new RuntimeException('Error'));

        expect($result)->toBe([]);
    });

    test('static value can be any type', function () {
        $strategy = FallbackStrategy::value(['key' => 'value', 'count' => 42]);

        $result = $strategy->execute(fn () => throw new RuntimeException('Error'));

        expect($result)->toBe(['key' => 'value', 'count' => 42]);
    });

    test('handles multiple exception types', function () {
        $strategy = new FallbackStrategy(
            fn () => 'fallback',
            handleOn: [RuntimeException::class, InvalidArgumentException::class]
        );

        $result1 = $strategy->execute(fn () => throw new RuntimeException('Runtime'));
        expect($result1)->toBe('fallback');

        $result2 = $strategy->execute(fn () => throw new InvalidArgumentException('Invalid'));
        expect($result2)->toBe('fallback');

        // LogicException should not be handled
        expect(fn () => $strategy->execute(fn () => throw new LogicException('Logic')))->toThrow(LogicException::class);
    });
});
