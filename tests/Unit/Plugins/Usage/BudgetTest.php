<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Usage\Data\Budget;
use JayI\Cortex\Plugins\Usage\Data\BudgetPeriod;
use JayI\Cortex\Plugins\Usage\Data\BudgetStatus;
use JayI\Cortex\Plugins\Usage\Data\UsageSummary;

describe('Budget', function () {
    test('creates cost-based budget', function () {
        $budget = Budget::cost(100.0);

        expect($budget->maxCost)->toBe(100.0);
        expect($budget->maxTokens)->toBeNull();
        expect($budget->maxRequests)->toBeNull();
        expect($budget->period)->toBe(BudgetPeriod::Monthly);
    });

    test('creates token-based budget', function () {
        $budget = Budget::tokens(1000000);

        expect($budget->maxTokens)->toBe(1000000);
        expect($budget->maxCost)->toBeNull();
    });

    test('creates request-based budget', function () {
        $budget = Budget::requests(1000);

        expect($budget->maxRequests)->toBe(1000);
        expect($budget->maxCost)->toBeNull();
    });

    test('supports different periods', function () {
        $daily = Budget::cost(10.0, BudgetPeriod::Daily);
        $weekly = Budget::cost(70.0, BudgetPeriod::Weekly);
        $yearly = Budget::cost(1200.0, BudgetPeriod::Yearly);

        expect($daily->period)->toBe(BudgetPeriod::Daily);
        expect($weekly->period)->toBe(BudgetPeriod::Weekly);
        expect($yearly->period)->toBe(BudgetPeriod::Yearly);
    });

    test('can be scoped to user', function () {
        $budget = Budget::cost(100.0, userId: 'user-123');

        expect($budget->userId)->toBe('user-123');
    });

    test('can be scoped to model', function () {
        $budget = Budget::cost(100.0, model: 'claude-3-opus');

        expect($budget->model)->toBe('claude-3-opus');
    });

    test('calculates period boundaries', function () {
        $budget = Budget::cost(100.0, BudgetPeriod::Monthly);

        $periodStart = $budget->getPeriodStart();
        $periodEnd = $budget->getPeriodEnd();

        expect($periodStart->format('d'))->toBe('01');
        expect($periodEnd->format('d'))->toBe($periodEnd->format('t')); // Last day of month
    });
});

describe('BudgetStatus', function () {
    test('checks exceeded cost budget', function () {
        $budget = Budget::cost(100.0);
        $usage = new UsageSummary(
            totalInputTokens: 100000,
            totalOutputTokens: 50000,
            totalCost: 150.0, // Over budget
            requestCount: 100,
            periodStart: new DateTimeImmutable,
            periodEnd: new DateTimeImmutable,
        );

        $status = BudgetStatus::check($budget, $usage);

        expect($status->exceeded)->toBeTrue();
        expect($status->usagePercentage)->toBe(100.0);
        expect($status->remainingCost)->toBe(0.0);
    });

    test('checks under budget', function () {
        $budget = Budget::cost(100.0);
        $usage = new UsageSummary(
            totalInputTokens: 10000,
            totalOutputTokens: 5000,
            totalCost: 50.0, // Under budget
            requestCount: 10,
            periodStart: new DateTimeImmutable,
            periodEnd: new DateTimeImmutable,
        );

        $status = BudgetStatus::check($budget, $usage);

        expect($status->exceeded)->toBeFalse();
        expect($status->usagePercentage)->toBe(50.0);
        expect($status->remainingCost)->toBe(50.0);
    });

    test('detects approaching limit', function () {
        $budget = Budget::cost(100.0);
        $usage = new UsageSummary(
            totalInputTokens: 80000,
            totalOutputTokens: 40000,
            totalCost: 85.0, // 85%
            requestCount: 85,
            periodStart: new DateTimeImmutable,
            periodEnd: new DateTimeImmutable,
        );

        $status = BudgetStatus::check($budget, $usage);

        expect($status->isApproachingLimit())->toBeTrue();
        expect($status->isApproachingLimit(90.0))->toBeFalse();
    });

    test('checks token budget', function () {
        $budget = Budget::tokens(1000);
        $usage = new UsageSummary(
            totalInputTokens: 800,
            totalOutputTokens: 400, // 1200 total, over 1000 limit
            totalCost: 0.5,
            requestCount: 10,
            periodStart: new DateTimeImmutable,
            periodEnd: new DateTimeImmutable,
        );

        $status = BudgetStatus::check($budget, $usage);

        expect($status->exceeded)->toBeTrue();
        expect($status->remainingTokens)->toBe(0);
    });
});
