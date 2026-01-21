<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Usage\Exceptions;

use Exception;
use JayI\Cortex\Plugins\Usage\Data\BudgetStatus;

/**
 * Exception thrown when a budget limit is exceeded.
 */
class BudgetExceededException extends Exception
{
    /**
     * @param  array<int, BudgetStatus>  $exceededBudgets
     */
    public function __construct(
        string $message = 'Budget limit exceeded',
        public readonly array $exceededBudgets = [],
    ) {
        parent::__construct($message);
    }

    /**
     * Create from budget statuses.
     *
     * @param  array<int, BudgetStatus>  $statuses
     */
    public static function fromStatuses(array $statuses): self
    {
        $budgetNames = array_map(
            fn (BudgetStatus $s) => $s->budget->id,
            $statuses
        );

        $message = 'Budget limits exceeded: '.implode(', ', $budgetNames);

        return new self($message, $statuses);
    }
}
