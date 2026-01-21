<?php

declare(strict_types=1);

use JayI\Cortex\Plugins\Usage\BudgetManager;
use JayI\Cortex\Plugins\Usage\Data\Budget;
use JayI\Cortex\Plugins\Usage\Data\BudgetPeriod;
use JayI\Cortex\Plugins\Usage\Data\UsageRecord;
use JayI\Cortex\Plugins\Usage\InMemoryUsageTracker;

describe('BudgetManager', function () {
    test('adds and retrieves budget', function () {
        $tracker = new InMemoryUsageTracker;
        $manager = new BudgetManager($tracker);

        $budget = Budget::cost(100.0, BudgetPeriod::Monthly);
        $manager->addBudget($budget);

        $retrieved = $manager->getBudget($budget->id);
        expect($retrieved)->toBe($budget);
    });

    test('removes budget', function () {
        $tracker = new InMemoryUsageTracker;
        $manager = new BudgetManager($tracker);

        $budget = Budget::cost(100.0, BudgetPeriod::Monthly);
        $manager->addBudget($budget);
        $manager->removeBudget($budget->id);

        expect($manager->getBudget($budget->id))->toBeNull();
    });

    test('returns null for non-existent budget', function () {
        $tracker = new InMemoryUsageTracker;
        $manager = new BudgetManager($tracker);

        expect($manager->getBudget('non-existent'))->toBeNull();
    });

    test('gets all budgets', function () {
        $tracker = new InMemoryUsageTracker;
        $manager = new BudgetManager($tracker);

        $budget1 = Budget::cost(100.0, BudgetPeriod::Monthly);
        $budget2 = Budget::tokens(1000000, BudgetPeriod::Daily);

        $manager->addBudget($budget1);
        $manager->addBudget($budget2);

        $all = $manager->getAllBudgets();
        expect($all)->toHaveCount(2);
        expect(array_keys($all))->toContain($budget1->id, $budget2->id);
    });

    test('gets budgets for user', function () {
        $tracker = new InMemoryUsageTracker;
        $manager = new BudgetManager($tracker);

        $globalBudget = Budget::cost(1000.0, BudgetPeriod::Monthly);
        $userBudget = Budget::cost(100.0, BudgetPeriod::Monthly, userId: 'user-123');
        $otherUserBudget = Budget::cost(100.0, BudgetPeriod::Monthly, userId: 'user-456');

        $manager->addBudget($globalBudget);
        $manager->addBudget($userBudget);
        $manager->addBudget($otherUserBudget);

        $userBudgets = $manager->getBudgetsForUser('user-123');

        // Should include global budget and user's own budget
        expect($userBudgets)->toHaveCount(2);
    });

    test('checks budget throws for non-existent budget', function () {
        $tracker = new InMemoryUsageTracker;
        $manager = new BudgetManager($tracker);

        expect(fn () => $manager->checkBudget('non-existent'))->toThrow(InvalidArgumentException::class);
    });

    test('checks budget status', function () {
        $tracker = new InMemoryUsageTracker;
        $manager = new BudgetManager($tracker);

        $budget = Budget::cost(100.0, BudgetPeriod::Monthly);
        $manager->addBudget($budget);

        // Add some usage
        $tracker->record(UsageRecord::create(
            model: 'claude-3-sonnet',
            inputTokens: 1000,
            outputTokens: 500,
            cost: 25.0,
        ));

        $status = $manager->checkBudget($budget->id);

        expect($status->exceeded)->toBeFalse();
        expect($status->usagePercentage)->toBe(25.0);
        expect($status->remainingCost)->toBe(75.0);
    });

    test('checks budgets for request', function () {
        $tracker = new InMemoryUsageTracker;
        $manager = new BudgetManager($tracker);

        $budget = Budget::cost(10.0, BudgetPeriod::Monthly, hardLimit: true);
        $manager->addBudget($budget);

        // Add usage that exceeds budget
        $tracker->record(UsageRecord::create(
            model: 'claude-3-sonnet',
            inputTokens: 10000,
            outputTokens: 5000,
            cost: 15.0,
        ));

        $exceeded = $manager->checkBudgetsForRequest();

        expect($exceeded)->toHaveCount(1);
        expect($exceeded[0]->exceeded)->toBeTrue();
    });

    test('checkBudgetsForRequest filters by user', function () {
        $tracker = new InMemoryUsageTracker;
        $manager = new BudgetManager($tracker);

        $userBudget = Budget::cost(10.0, BudgetPeriod::Monthly, userId: 'user-123', hardLimit: true);
        $manager->addBudget($userBudget);

        // This request is for a different user, so budget should not apply
        $exceeded = $manager->checkBudgetsForRequest(userId: 'user-456');

        expect($exceeded)->toHaveCount(0);
    });

    test('checkBudgetsForRequest filters by model', function () {
        $tracker = new InMemoryUsageTracker;
        $manager = new BudgetManager($tracker);

        $modelBudget = Budget::cost(10.0, BudgetPeriod::Monthly, model: 'claude-3-opus', hardLimit: true);
        $manager->addBudget($modelBudget);

        // This request is for a different model, so budget should not apply
        $exceeded = $manager->checkBudgetsForRequest(model: 'claude-3-sonnet');

        expect($exceeded)->toHaveCount(0);
    });

    test('checks all budgets', function () {
        $tracker = new InMemoryUsageTracker;
        $manager = new BudgetManager($tracker);

        $budget1 = Budget::cost(100.0, BudgetPeriod::Monthly);
        $budget2 = Budget::tokens(1000000, BudgetPeriod::Daily);

        $manager->addBudget($budget1);
        $manager->addBudget($budget2);

        $statuses = $manager->checkAllBudgets();

        expect($statuses)->toHaveCount(2);
        expect(array_keys($statuses))->toContain($budget1->id, $budget2->id);
    });

    test('soft limit budgets do not appear in exceeded list', function () {
        $tracker = new InMemoryUsageTracker;
        $manager = new BudgetManager($tracker);

        $softBudget = Budget::cost(10.0, BudgetPeriod::Monthly, hardLimit: false);
        $manager->addBudget($softBudget);

        // Add usage that exceeds budget
        $tracker->record(UsageRecord::create(
            model: 'claude-3-sonnet',
            inputTokens: 10000,
            outputTokens: 5000,
            cost: 15.0,
        ));

        $exceeded = $manager->checkBudgetsForRequest();

        // Should be empty because it's a soft limit
        expect($exceeded)->toHaveCount(0);
    });
});
