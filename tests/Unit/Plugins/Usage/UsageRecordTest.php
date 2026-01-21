<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Usage\Data\UsageRecord;

describe('UsageRecord', function () {
    test('creates a usage record', function () {
        $record = UsageRecord::create(
            model: 'claude-3-sonnet',
            inputTokens: 100,
            outputTokens: 50,
            cost: 0.0045,
        );

        expect($record->model)->toBe('claude-3-sonnet');
        expect($record->inputTokens)->toBe(100);
        expect($record->outputTokens)->toBe(50);
        expect($record->cost)->toBe(0.0045);
        expect($record->id)->toStartWith('usage_');
    });

    test('calculates total tokens', function () {
        $record = UsageRecord::create(
            model: 'claude-3-sonnet',
            inputTokens: 100,
            outputTokens: 50,
            cost: 0.0045,
        );

        expect($record->totalTokens())->toBe(150);
    });

    test('includes optional fields', function () {
        $record = UsageRecord::create(
            model: 'claude-3-sonnet',
            inputTokens: 100,
            outputTokens: 50,
            cost: 0.0045,
            requestId: 'req-123',
            userId: 'user-456',
            sessionId: 'session-789',
            metadata: ['source' => 'api'],
        );

        expect($record->requestId)->toBe('req-123');
        expect($record->userId)->toBe('user-456');
        expect($record->sessionId)->toBe('session-789');
        expect($record->metadata)->toBe(['source' => 'api']);
    });
});
