<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Usage\Contracts;

use DateTimeImmutable;
use JayI\Cortex\Plugins\Usage\Data\UsageRecord;
use JayI\Cortex\Plugins\Usage\Data\UsageSummary;

interface UsageTrackerContract
{
    /**
     * Record a usage event.
     */
    public function record(UsageRecord $record): void;

    /**
     * Get usage summary for a time period.
     */
    public function getSummary(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        ?string $userId = null,
        ?string $model = null,
    ): UsageSummary;

    /**
     * Get usage records for a time period.
     *
     * @return array<int, UsageRecord>
     */
    public function getRecords(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        ?string $userId = null,
        ?string $model = null,
        ?int $limit = null,
    ): array;

    /**
     * Get the most recent usage records.
     *
     * @return array<int, UsageRecord>
     */
    public function getRecentRecords(int $limit = 10, ?string $userId = null): array;

    /**
     * Clear all usage records (for testing).
     */
    public function clear(): void;
}
