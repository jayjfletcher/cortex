<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Usage\Data;

use DateTimeImmutable;
use Spatie\LaravelData\Data;

/**
 * Budget configuration for usage limits.
 */
class Budget extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly ?float $maxCost = null,
        public readonly ?int $maxTokens = null,
        public readonly ?int $maxRequests = null,
        public readonly BudgetPeriod $period = BudgetPeriod::Monthly,
        public readonly ?string $userId = null,
        public readonly ?string $model = null,
        public readonly bool $hardLimit = true,
        /** @var array<string, mixed> */
        public readonly array $metadata = [],
    ) {}

    /**
     * Create a cost-based budget.
     */
    public static function cost(
        float $maxCost,
        BudgetPeriod $period = BudgetPeriod::Monthly,
        ?string $userId = null,
        ?string $model = null,
        bool $hardLimit = true,
    ): self {
        return new self(
            id: uniqid('budget_', true),
            maxCost: $maxCost,
            period: $period,
            userId: $userId,
            model: $model,
            hardLimit: $hardLimit,
        );
    }

    /**
     * Create a token-based budget.
     */
    public static function tokens(
        int $maxTokens,
        BudgetPeriod $period = BudgetPeriod::Monthly,
        ?string $userId = null,
        ?string $model = null,
        bool $hardLimit = true,
    ): self {
        return new self(
            id: uniqid('budget_', true),
            maxTokens: $maxTokens,
            period: $period,
            userId: $userId,
            model: $model,
            hardLimit: $hardLimit,
        );
    }

    /**
     * Create a request-based budget.
     */
    public static function requests(
        int $maxRequests,
        BudgetPeriod $period = BudgetPeriod::Monthly,
        ?string $userId = null,
        ?string $model = null,
        bool $hardLimit = true,
    ): self {
        return new self(
            id: uniqid('budget_', true),
            maxRequests: $maxRequests,
            period: $period,
            userId: $userId,
            model: $model,
            hardLimit: $hardLimit,
        );
    }

    /**
     * Get the period start date for a given point in time.
     */
    public function getPeriodStart(?DateTimeImmutable $at = null): DateTimeImmutable
    {
        $at ??= new DateTimeImmutable;

        return match ($this->period) {
            BudgetPeriod::Daily => $at->setTime(0, 0, 0),
            BudgetPeriod::Weekly => $at->modify('monday this week')->setTime(0, 0, 0),
            BudgetPeriod::Monthly => $at->setDate((int) $at->format('Y'), (int) $at->format('m'), 1)->setTime(0, 0, 0),
            BudgetPeriod::Yearly => $at->setDate((int) $at->format('Y'), 1, 1)->setTime(0, 0, 0),
        };
    }

    /**
     * Get the period end date for a given point in time.
     */
    public function getPeriodEnd(?DateTimeImmutable $at = null): DateTimeImmutable
    {
        $at ??= new DateTimeImmutable;

        return match ($this->period) {
            BudgetPeriod::Daily => $at->setTime(23, 59, 59),
            BudgetPeriod::Weekly => $at->modify('sunday this week')->setTime(23, 59, 59),
            BudgetPeriod::Monthly => $at->modify('last day of this month')->setTime(23, 59, 59),
            BudgetPeriod::Yearly => $at->setDate((int) $at->format('Y'), 12, 31)->setTime(23, 59, 59),
        };
    }
}
