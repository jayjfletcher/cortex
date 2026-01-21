<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Usage\Data\UsageRecord;
use JayI\Cortex\Plugins\Usage\Data\UsageSummary;

describe('UsageSummary', function () {
    test('creates empty summary', function () {
        $start = new DateTimeImmutable('2024-01-01');
        $end = new DateTimeImmutable('2024-01-31');

        $summary = UsageSummary::zero($start, $end);

        expect($summary->totalInputTokens)->toBe(0);
        expect($summary->totalOutputTokens)->toBe(0);
        expect($summary->totalCost)->toBe(0.0);
        expect($summary->requestCount)->toBe(0);
    });

    test('creates summary from records', function () {
        $start = new DateTimeImmutable('2024-01-01');
        $end = new DateTimeImmutable('2024-01-31');

        $records = [
            UsageRecord::create('claude-3-sonnet', 100, 50, 0.0045),
            UsageRecord::create('claude-3-sonnet', 200, 100, 0.009),
            UsageRecord::create('claude-3-opus', 50, 25, 0.01),
        ];

        $summary = UsageSummary::fromRecords($records, $start, $end);

        expect($summary->totalInputTokens)->toBe(350);
        expect($summary->totalOutputTokens)->toBe(175);
        expect($summary->totalCost)->toBe(0.0235);
        expect($summary->requestCount)->toBe(3);
    });

    test('calculates total tokens', function () {
        $start = new DateTimeImmutable('2024-01-01');
        $end = new DateTimeImmutable('2024-01-31');

        $summary = new UsageSummary(
            totalInputTokens: 1000,
            totalOutputTokens: 500,
            totalCost: 1.5,
            requestCount: 10,
            periodStart: $start,
            periodEnd: $end,
        );

        expect($summary->totalTokens())->toBe(1500);
    });

    test('calculates averages', function () {
        $start = new DateTimeImmutable('2024-01-01');
        $end = new DateTimeImmutable('2024-01-31');

        $summary = new UsageSummary(
            totalInputTokens: 1000,
            totalOutputTokens: 500,
            totalCost: 10.0,
            requestCount: 10,
            periodStart: $start,
            periodEnd: $end,
        );

        expect($summary->averageTokensPerRequest())->toBe(150.0);
        expect($summary->averageCostPerRequest())->toBe(1.0);
    });

    test('handles zero requests', function () {
        $start = new DateTimeImmutable('2024-01-01');
        $end = new DateTimeImmutable('2024-01-31');

        $summary = UsageSummary::zero($start, $end);

        expect($summary->averageTokensPerRequest())->toBe(0.0);
        expect($summary->averageCostPerRequest())->toBe(0.0);
    });

    test('tracks tokens by model', function () {
        $start = new DateTimeImmutable('2024-01-01');
        $end = new DateTimeImmutable('2024-01-31');

        $records = [
            UsageRecord::create('claude-3-sonnet', 100, 50, 0.0045),
            UsageRecord::create('claude-3-opus', 200, 100, 0.03),
        ];

        $summary = UsageSummary::fromRecords($records, $start, $end);

        expect($summary->tokensByModel)->toBe([
            'claude-3-sonnet' => 150,
            'claude-3-opus' => 300,
        ]);
    });
});
