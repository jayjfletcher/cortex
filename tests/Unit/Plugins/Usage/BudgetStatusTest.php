<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Usage\Data\Budget;
use JayI\Cortex\Plugins\Usage\Data\BudgetPeriod;
use JayI\Cortex\Plugins\Usage\Data\BudgetStatus;
use JayI\Cortex\Plugins\Usage\Data\UsageRecord;
use JayI\Cortex\Plugins\Usage\Data\UsageSummary;

describe('BudgetStatus', function () {
    test('checks cost budget not exceeded', function () {
        $budget = Budget::cost(100.0, BudgetPeriod::Monthly);
        $usage = UsageSummary::fromRecords([
            UsageRecord::create('model', 1000, 500, 25.0),
        ], new DateTimeImmutable, new DateTimeImmutable);

        $status = BudgetStatus::check($budget, $usage);

        expect($status->exceeded)->toBeFalse();
        expect($status->usagePercentage)->toBe(25.0);
        expect($status->remainingCost)->toBe(75.0);
    });

    test('checks cost budget exceeded', function () {
        $budget = Budget::cost(50.0, BudgetPeriod::Monthly);
        $usage = UsageSummary::fromRecords([
            UsageRecord::create('model', 1000, 500, 75.0),
        ], new DateTimeImmutable, new DateTimeImmutable);

        $status = BudgetStatus::check($budget, $usage);

        expect($status->exceeded)->toBeTrue();
        expect($status->usagePercentage)->toBe(100.0); // Capped at 100
        expect($status->remainingCost)->toBe(0.0);
    });

    test('checks token budget not exceeded', function () {
        $budget = Budget::tokens(10000, BudgetPeriod::Daily);
        $usage = UsageSummary::fromRecords([
            UsageRecord::create('model', 1000, 500, 0.01),
        ], new DateTimeImmutable, new DateTimeImmutable);

        $status = BudgetStatus::check($budget, $usage);

        expect($status->exceeded)->toBeFalse();
        expect($status->usagePercentage)->toBe(15.0); // 1500/10000 = 15%
        expect($status->remainingTokens)->toBe(8500);
    });

    test('checks token budget exceeded', function () {
        $budget = Budget::tokens(1000, BudgetPeriod::Daily);
        $usage = UsageSummary::fromRecords([
            UsageRecord::create('model', 1000, 500, 0.01),
        ], new DateTimeImmutable, new DateTimeImmutable);

        $status = BudgetStatus::check($budget, $usage);

        expect($status->exceeded)->toBeTrue();
        expect($status->remainingTokens)->toBe(0);
    });

    test('checks request budget not exceeded', function () {
        $budget = Budget::requests(100, BudgetPeriod::Weekly);
        $usage = UsageSummary::fromRecords([
            UsageRecord::create('model', 1000, 500, 0.01),
            UsageRecord::create('model', 1000, 500, 0.01),
        ], new DateTimeImmutable, new DateTimeImmutable);

        $status = BudgetStatus::check($budget, $usage);

        expect($status->exceeded)->toBeFalse();
        expect($status->usagePercentage)->toBe(2.0); // 2/100 = 2%
        expect($status->remainingRequests)->toBe(98);
    });

    test('checks request budget exceeded', function () {
        $budget = Budget::requests(2, BudgetPeriod::Weekly);
        $records = [];
        for ($i = 0; $i < 5; $i++) {
            $records[] = UsageRecord::create('model', 100, 50, 0.01);
        }
        $usage = UsageSummary::fromRecords($records, new DateTimeImmutable, new DateTimeImmutable);

        $status = BudgetStatus::check($budget, $usage);

        expect($status->exceeded)->toBeTrue();
        expect($status->remainingRequests)->toBe(0);
    });

    test('isApproachingLimit returns true when near threshold', function () {
        $budget = Budget::cost(100.0, BudgetPeriod::Monthly);
        $usage = UsageSummary::fromRecords([
            UsageRecord::create('model', 1000, 500, 85.0),
        ], new DateTimeImmutable, new DateTimeImmutable);

        $status = BudgetStatus::check($budget, $usage);

        expect($status->isApproachingLimit(80.0))->toBeTrue();
        expect($status->isApproachingLimit(90.0))->toBeFalse();
    });

    test('isApproachingLimit returns false when exceeded', function () {
        $budget = Budget::cost(50.0, BudgetPeriod::Monthly);
        $usage = UsageSummary::fromRecords([
            UsageRecord::create('model', 1000, 500, 75.0),
        ], new DateTimeImmutable, new DateTimeImmutable);

        $status = BudgetStatus::check($budget, $usage);

        // Exceeded is true, so isApproachingLimit should be false
        expect($status->isApproachingLimit(80.0))->toBeFalse();
    });

    test('usagePercentage uses max of all limits', function () {
        // Budget with multiple limits
        $budget = new Budget(
            id: 'test',
            period: BudgetPeriod::Monthly,
            maxCost: 100.0,
            maxTokens: 10000,
            maxRequests: 100,
        );

        // Usage that hits token limit hardest (50%)
        $usage = UsageSummary::fromRecords([
            UsageRecord::create('model', 4000, 1000, 10.0), // 5000 tokens = 50%, $10 = 10%, 1 request = 1%
        ], new DateTimeImmutable, new DateTimeImmutable);

        $status = BudgetStatus::check($budget, $usage);

        // Should use highest percentage (token usage at 50%)
        expect($status->usagePercentage)->toBe(50.0);
    });

    test('returns zero usage with no limits', function () {
        // Budget with no limits (all null)
        $budget = new Budget(
            id: 'test',
            period: BudgetPeriod::Monthly,
        );

        $usage = UsageSummary::fromRecords([
            UsageRecord::create('model', 1000, 500, 10.0),
        ], new DateTimeImmutable, new DateTimeImmutable);

        $status = BudgetStatus::check($budget, $usage);

        expect($status->exceeded)->toBeFalse();
        expect($status->usagePercentage)->toBe(0.0);
    });
});
