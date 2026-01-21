<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Resilience\CircuitState;

describe('CircuitState', function () {
    test('closed state allows requests', function () {
        $state = CircuitState::Closed;

        expect($state->allowsRequests())->toBeTrue();
        expect($state->value)->toBe('closed');
    });

    test('open state blocks requests', function () {
        $state = CircuitState::Open;

        expect($state->allowsRequests())->toBeFalse();
        expect($state->value)->toBe('open');
    });

    test('half-open state allows requests', function () {
        $state = CircuitState::HalfOpen;

        expect($state->allowsRequests())->toBeTrue();
        expect($state->value)->toBe('half_open');
    });
});
