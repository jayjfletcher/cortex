<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Usage\Data\Budget;
use JayI\Cortex\Plugins\Usage\Data\BudgetPeriod;
use JayI\Cortex\Plugins\Usage\Data\BudgetStatus;
use JayI\Cortex\Plugins\Usage\Data\UsageSummary;
use JayI\Cortex\Plugins\Usage\Exceptions\BudgetExceededException;

describe('BudgetExceededException', function () {
    it('creates with default message', function () {
        $exception = new BudgetExceededException();

        expect($exception->getMessage())->toBe('Budget limit exceeded');
        expect($exception->exceededBudgets)->toBe([]);
    });

    it('creates with custom message', function () {
        $exception = new BudgetExceededException('Custom budget error');

        expect($exception->getMessage())->toBe('Custom budget error');
    });

    it('creates with exceeded budgets', function () {
        $budget = new Budget(
            id: 'daily-budget',
            maxCost: 100.0,
            period: BudgetPeriod::Daily,
        );

        $usage = new UsageSummary(
            totalInputTokens: 10000,
            totalOutputTokens: 5000,
            totalCost: 150.0,
            requestCount: 10,
            periodStart: new DateTimeImmutable('2024-01-01'),
            periodEnd: new DateTimeImmutable('2024-01-02'),
        );

        $status = BudgetStatus::check($budget, $usage);

        $exception = new BudgetExceededException('Over budget', [$status]);

        expect($exception->exceededBudgets)->toHaveCount(1);
        expect($exception->exceededBudgets[0])->toBe($status);
        expect($exception->exceededBudgets[0]->exceeded)->toBeTrue();
    });

    it('creates from statuses', function () {
        $budget1 = new Budget(
            id: 'daily-limit',
            maxCost: 100.0,
            period: BudgetPeriod::Daily,
        );

        $budget2 = new Budget(
            id: 'monthly-limit',
            maxCost: 1000.0,
            period: BudgetPeriod::Monthly,
        );

        $usage1 = new UsageSummary(
            totalInputTokens: 10000,
            totalOutputTokens: 5000,
            totalCost: 120.0,
            requestCount: 10,
            periodStart: new DateTimeImmutable('2024-01-01'),
            periodEnd: new DateTimeImmutable('2024-01-02'),
        );

        $usage2 = new UsageSummary(
            totalInputTokens: 100000,
            totalOutputTokens: 50000,
            totalCost: 1100.0,
            requestCount: 100,
            periodStart: new DateTimeImmutable('2024-01-01'),
            periodEnd: new DateTimeImmutable('2024-02-01'),
        );

        $status1 = BudgetStatus::check($budget1, $usage1);
        $status2 = BudgetStatus::check($budget2, $usage2);

        $exception = BudgetExceededException::fromStatuses([$status1, $status2]);

        expect($exception->getMessage())->toContain('daily-limit');
        expect($exception->getMessage())->toContain('monthly-limit');
        expect($exception->exceededBudgets)->toHaveCount(2);
    });

    it('creates from single status', function () {
        $budget = new Budget(
            id: 'test-budget',
            maxCost: 50.0,
            period: BudgetPeriod::Daily,
        );

        $usage = new UsageSummary(
            totalInputTokens: 5000,
            totalOutputTokens: 2500,
            totalCost: 75.0,
            requestCount: 5,
            periodStart: new DateTimeImmutable('2024-01-01'),
            periodEnd: new DateTimeImmutable('2024-01-02'),
        );

        $status = BudgetStatus::check($budget, $usage);

        $exception = BudgetExceededException::fromStatuses([$status]);

        expect($exception->getMessage())->toContain('test-budget');
        expect($exception->exceededBudgets)->toHaveCount(1);
        expect($exception->exceededBudgets[0]->exceeded)->toBeTrue();
    });
});
