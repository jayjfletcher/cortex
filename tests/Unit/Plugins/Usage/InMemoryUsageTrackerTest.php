<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Usage\Data\UsageRecord;
use JayI\Cortex\Plugins\Usage\InMemoryUsageTracker;

describe('InMemoryUsageTracker', function () {
    test('records and retrieves usage', function () {
        $tracker = new InMemoryUsageTracker;

        $record = UsageRecord::create(
            model: 'claude-3-sonnet',
            inputTokens: 1000,
            outputTokens: 500,
            cost: 0.01,
        );

        $tracker->record($record);

        $all = $tracker->all();
        expect($all)->toHaveCount(1);
        expect($all[0])->toBe($record);
    });

    test('gets summary for time period', function () {
        $tracker = new InMemoryUsageTracker;

        $now = new DateTimeImmutable;
        $record1 = UsageRecord::create(
            model: 'claude-3-sonnet',
            inputTokens: 1000,
            outputTokens: 500,
            cost: 0.01,
        );
        $record2 = UsageRecord::create(
            model: 'claude-3-sonnet',
            inputTokens: 2000,
            outputTokens: 1000,
            cost: 0.02,
        );

        $tracker->record($record1);
        $tracker->record($record2);

        $summary = $tracker->getSummary(
            start: $now->modify('-1 hour'),
            end: $now->modify('+1 hour'),
        );

        expect($summary->totalInputTokens)->toBe(3000);
        expect($summary->totalOutputTokens)->toBe(1500);
        expect($summary->totalCost)->toBe(0.03);
        expect($summary->requestCount)->toBe(2);
    });

    test('filters by user in summary', function () {
        $tracker = new InMemoryUsageTracker;
        $now = new DateTimeImmutable;

        $tracker->record(UsageRecord::create(
            model: 'claude-3-sonnet',
            inputTokens: 1000,
            outputTokens: 500,
            cost: 0.01,
            userId: 'user-123',
        ));
        $tracker->record(UsageRecord::create(
            model: 'claude-3-sonnet',
            inputTokens: 2000,
            outputTokens: 1000,
            cost: 0.02,
            userId: 'user-456',
        ));

        $summary = $tracker->getSummary(
            start: $now->modify('-1 hour'),
            end: $now->modify('+1 hour'),
            userId: 'user-123',
        );

        expect($summary->totalInputTokens)->toBe(1000);
        expect($summary->requestCount)->toBe(1);
    });

    test('filters by model in summary', function () {
        $tracker = new InMemoryUsageTracker;
        $now = new DateTimeImmutable;

        $tracker->record(UsageRecord::create(
            model: 'claude-3-sonnet',
            inputTokens: 1000,
            outputTokens: 500,
            cost: 0.01,
        ));
        $tracker->record(UsageRecord::create(
            model: 'claude-3-opus',
            inputTokens: 2000,
            outputTokens: 1000,
            cost: 0.05,
        ));

        $summary = $tracker->getSummary(
            start: $now->modify('-1 hour'),
            end: $now->modify('+1 hour'),
            model: 'claude-3-opus',
        );

        expect($summary->totalInputTokens)->toBe(2000);
        expect($summary->requestCount)->toBe(1);
    });

    test('gets records with limit', function () {
        $tracker = new InMemoryUsageTracker;
        $now = new DateTimeImmutable;

        for ($i = 0; $i < 10; $i++) {
            $tracker->record(UsageRecord::create(
                model: 'claude-3-sonnet',
                inputTokens: 100,
                outputTokens: 50,
                cost: 0.001,
            ));
        }

        $records = $tracker->getRecords(
            start: $now->modify('-1 hour'),
            end: $now->modify('+1 hour'),
            limit: 5,
        );

        expect($records)->toHaveCount(5);
    });

    test('gets records filtered by time range', function () {
        $tracker = new InMemoryUsageTracker;

        // Create a record with a specific timestamp in the past
        $oldRecord = new UsageRecord(
            id: 'usage_old',
            model: 'claude-3-sonnet',
            inputTokens: 1000,
            outputTokens: 500,
            cost: 0.01,
            timestamp: new DateTimeImmutable('-2 days'),
        );
        $tracker->record($oldRecord);

        // Create a recent record
        $recentRecord = UsageRecord::create(
            model: 'claude-3-sonnet',
            inputTokens: 2000,
            outputTokens: 1000,
            cost: 0.02,
        );
        $tracker->record($recentRecord);

        $now = new DateTimeImmutable;
        $records = $tracker->getRecords(
            start: $now->modify('-1 hour'),
            end: $now->modify('+1 hour'),
        );

        expect($records)->toHaveCount(1);
        expect($records[0]->inputTokens)->toBe(2000);
    });

    test('gets recent records', function () {
        $tracker = new InMemoryUsageTracker;

        for ($i = 0; $i < 20; $i++) {
            $tracker->record(UsageRecord::create(
                model: 'claude-3-sonnet',
                inputTokens: 100 * ($i + 1),
                outputTokens: 50,
                cost: 0.001,
            ));
        }

        $recent = $tracker->getRecentRecords(5);

        expect($recent)->toHaveCount(5);
    });

    test('gets recent records filtered by user', function () {
        $tracker = new InMemoryUsageTracker;

        for ($i = 0; $i < 5; $i++) {
            $tracker->record(UsageRecord::create(
                model: 'claude-3-sonnet',
                inputTokens: 100,
                outputTokens: 50,
                cost: 0.001,
                userId: 'user-123',
            ));
        }

        for ($i = 0; $i < 10; $i++) {
            $tracker->record(UsageRecord::create(
                model: 'claude-3-sonnet',
                inputTokens: 200,
                outputTokens: 100,
                cost: 0.002,
                userId: 'user-456',
            ));
        }

        $recent = $tracker->getRecentRecords(10, userId: 'user-123');

        expect($recent)->toHaveCount(5);
        foreach ($recent as $record) {
            expect($record->userId)->toBe('user-123');
        }
    });

    test('clears all records', function () {
        $tracker = new InMemoryUsageTracker;

        $tracker->record(UsageRecord::create(
            model: 'claude-3-sonnet',
            inputTokens: 1000,
            outputTokens: 500,
            cost: 0.01,
        ));

        expect($tracker->all())->toHaveCount(1);

        $tracker->clear();

        expect($tracker->all())->toHaveCount(0);
    });

    test('filters out records outside time range', function () {
        $tracker = new InMemoryUsageTracker;

        // Create records with specific timestamps
        $record = new UsageRecord(
            id: 'usage_test',
            model: 'claude-3-sonnet',
            inputTokens: 1000,
            outputTokens: 500,
            cost: 0.01,
            timestamp: new DateTimeImmutable('2024-01-15 12:00:00'),
        );
        $tracker->record($record);

        // Query for a different time range
        $records = $tracker->getRecords(
            start: new DateTimeImmutable('2024-02-01 00:00:00'),
            end: new DateTimeImmutable('2024-02-28 23:59:59'),
        );

        expect($records)->toHaveCount(0);
    });
});
