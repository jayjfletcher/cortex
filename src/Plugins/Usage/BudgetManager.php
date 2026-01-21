<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Usage;

use JayI\Cortex\Plugins\Usage\Contracts\BudgetManagerContract;
use JayI\Cortex\Plugins\Usage\Contracts\UsageTrackerContract;
use JayI\Cortex\Plugins\Usage\Data\Budget;
use JayI\Cortex\Plugins\Usage\Data\BudgetStatus;

/**
 * Manages usage budgets and limits.
 */
class BudgetManager implements BudgetManagerContract
{
    /** @var array<string, Budget> */
    protected array $budgets = [];

    public function __construct(
        protected UsageTrackerContract $tracker,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function addBudget(Budget $budget): void
    {
        $this->budgets[$budget->id] = $budget;
    }

    /**
     * {@inheritdoc}
     */
    public function removeBudget(string $budgetId): void
    {
        unset($this->budgets[$budgetId]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBudget(string $budgetId): ?Budget
    {
        return $this->budgets[$budgetId] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllBudgets(): array
    {
        return $this->budgets;
    }

    /**
     * {@inheritdoc}
     */
    public function getBudgetsForUser(?string $userId): array
    {
        return array_filter(
            $this->budgets,
            fn (Budget $b) => $b->userId === $userId || $b->userId === null
        );
    }

    /**
     * {@inheritdoc}
     */
    public function checkBudget(string $budgetId): BudgetStatus
    {
        $budget = $this->getBudget($budgetId);

        if ($budget === null) {
            throw new \InvalidArgumentException("Budget not found: {$budgetId}");
        }

        $periodStart = $budget->getPeriodStart();
        $periodEnd = $budget->getPeriodEnd();

        $usage = $this->tracker->getSummary(
            start: $periodStart,
            end: $periodEnd,
            userId: $budget->userId,
            model: $budget->model,
        );

        return BudgetStatus::check($budget, $usage);
    }

    /**
     * {@inheritdoc}
     */
    public function checkBudgetsForRequest(
        ?string $userId = null,
        ?string $model = null,
    ): array {
        $exceeded = [];

        foreach ($this->budgets as $budget) {
            // Check if budget applies to this request
            if ($budget->userId !== null && $budget->userId !== $userId) {
                continue;
            }

            if ($budget->model !== null && $budget->model !== $model) {
                continue;
            }

            $status = $this->checkBudget($budget->id);

            if ($status->exceeded && $budget->hardLimit) {
                $exceeded[] = $status;
            }
        }

        return $exceeded;
    }

    /**
     * {@inheritdoc}
     */
    public function checkAllBudgets(): array
    {
        $statuses = [];

        foreach ($this->budgets as $budgetId => $budget) {
            $statuses[$budgetId] = $this->checkBudget($budgetId);
        }

        return $statuses;
    }
}
