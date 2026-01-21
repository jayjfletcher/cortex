<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Usage\Data;

use Spatie\LaravelData\Data;

/**
 * Current status of a budget.
 */
class BudgetStatus extends Data
{
    public function __construct(
        public readonly Budget $budget,
        public readonly UsageSummary $usage,
        public readonly bool $exceeded,
        public readonly float $usagePercentage,
        public readonly ?float $remainingCost = null,
        public readonly ?int $remainingTokens = null,
        public readonly ?int $remainingRequests = null,
    ) {}

    /**
     * Check the status of a budget against current usage.
     */
    public static function check(Budget $budget, UsageSummary $usage): self
    {
        $exceeded = false;
        $usagePercentage = 0.0;
        $remainingCost = null;
        $remainingTokens = null;
        $remainingRequests = null;

        $percentages = [];

        if ($budget->maxCost !== null) {
            $remainingCost = max(0, $budget->maxCost - $usage->totalCost);
            $costPercentage = ($usage->totalCost / $budget->maxCost) * 100;
            $percentages[] = $costPercentage;

            if ($usage->totalCost >= $budget->maxCost) {
                $exceeded = true;
            }
        }

        if ($budget->maxTokens !== null) {
            $remainingTokens = max(0, $budget->maxTokens - $usage->totalTokens());
            $tokenPercentage = ($usage->totalTokens() / $budget->maxTokens) * 100;
            $percentages[] = $tokenPercentage;

            if ($usage->totalTokens() >= $budget->maxTokens) {
                $exceeded = true;
            }
        }

        if ($budget->maxRequests !== null) {
            $remainingRequests = max(0, $budget->maxRequests - $usage->requestCount);
            $requestPercentage = ($usage->requestCount / $budget->maxRequests) * 100;
            $percentages[] = $requestPercentage;

            if ($usage->requestCount >= $budget->maxRequests) {
                $exceeded = true;
            }
        }

        if (! empty($percentages)) {
            $usagePercentage = max($percentages);
        }

        return new self(
            budget: $budget,
            usage: $usage,
            exceeded: $exceeded,
            usagePercentage: min(100, $usagePercentage),
            remainingCost: $remainingCost,
            remainingTokens: $remainingTokens,
            remainingRequests: $remainingRequests,
        );
    }

    /**
     * Check if budget is approaching limit (default 80%).
     */
    public function isApproachingLimit(float $threshold = 80.0): bool
    {
        return $this->usagePercentage >= $threshold && ! $this->exceeded;
    }
}
