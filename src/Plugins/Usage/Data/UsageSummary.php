<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Usage\Data;

use DateTimeImmutable;
use Spatie\LaravelData\Data;

/**
 * Summary of usage over a time period.
 */
class UsageSummary extends Data
{
    public function __construct(
        public readonly int $totalInputTokens,
        public readonly int $totalOutputTokens,
        public readonly float $totalCost,
        public readonly int $requestCount,
        public readonly DateTimeImmutable $periodStart,
        public readonly DateTimeImmutable $periodEnd,
        /** @var array<string, int> */
        public readonly array $tokensByModel = [],
        /** @var array<string, float> */
        public readonly array $costByModel = [],
    ) {}

    /**
     * Get total tokens (input + output).
     */
    public function totalTokens(): int
    {
        return $this->totalInputTokens + $this->totalOutputTokens;
    }

    /**
     * Get average tokens per request.
     */
    public function averageTokensPerRequest(): float
    {
        if ($this->requestCount === 0) {
            return 0.0;
        }

        return $this->totalTokens() / $this->requestCount;
    }

    /**
     * Get average cost per request.
     */
    public function averageCostPerRequest(): float
    {
        if ($this->requestCount === 0) {
            return 0.0;
        }

        return $this->totalCost / $this->requestCount;
    }

    /**
     * Create a zero summary for a period.
     */
    public static function zero(
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
    ): self {
        return new self(
            totalInputTokens: 0,
            totalOutputTokens: 0,
            totalCost: 0.0,
            requestCount: 0,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
        );
    }

    /**
     * Create a summary from a collection of records.
     *
     * @param  array<int, UsageRecord>  $records
     */
    public static function fromRecords(
        array $records,
        DateTimeImmutable $periodStart,
        DateTimeImmutable $periodEnd,
    ): self {
        $totalInputTokens = 0;
        $totalOutputTokens = 0;
        $totalCost = 0.0;
        $tokensByModel = [];
        $costByModel = [];

        foreach ($records as $record) {
            $totalInputTokens += $record->inputTokens;
            $totalOutputTokens += $record->outputTokens;
            $totalCost += $record->cost;

            $tokensByModel[$record->model] = ($tokensByModel[$record->model] ?? 0) + $record->totalTokens();
            $costByModel[$record->model] = ($costByModel[$record->model] ?? 0.0) + $record->cost;
        }

        return new self(
            totalInputTokens: $totalInputTokens,
            totalOutputTokens: $totalOutputTokens,
            totalCost: $totalCost,
            requestCount: count($records),
            periodStart: $periodStart,
            periodEnd: $periodEnd,
            tokensByModel: $tokensByModel,
            costByModel: $costByModel,
        );
    }
}
