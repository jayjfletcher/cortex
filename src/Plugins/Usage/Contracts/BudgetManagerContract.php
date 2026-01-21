<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Usage\Contracts;

use JayI\Cortex\Plugins\Usage\Data\Budget;
use JayI\Cortex\Plugins\Usage\Data\BudgetStatus;

interface BudgetManagerContract
{
    /**
     * Add a budget to be tracked.
     */
    public function addBudget(Budget $budget): void;

    /**
     * Remove a budget.
     */
    public function removeBudget(string $budgetId): void;

    /**
     * Get a budget by ID.
     */
    public function getBudget(string $budgetId): ?Budget;

    /**
     * Get all budgets.
     *
     * @return array<string, Budget>
     */
    public function getAllBudgets(): array;

    /**
     * Get budgets for a specific user.
     *
     * @return array<string, Budget>
     */
    public function getBudgetsForUser(?string $userId): array;

    /**
     * Check the status of a specific budget.
     */
    public function checkBudget(string $budgetId): BudgetStatus;

    /**
     * Check if any applicable budget would be exceeded by a request.
     *
     * @return array<int, BudgetStatus> Array of exceeded budget statuses
     */
    public function checkBudgetsForRequest(
        ?string $userId = null,
        ?string $model = null,
    ): array;

    /**
     * Check all budget statuses.
     *
     * @return array<string, BudgetStatus>
     */
    public function checkAllBudgets(): array;
}
