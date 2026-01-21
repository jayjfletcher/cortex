<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Usage;

use DateTimeImmutable;
use JayI\Cortex\Plugins\Usage\Contracts\UsageTrackerContract;
use JayI\Cortex\Plugins\Usage\Data\UsageRecord;
use JayI\Cortex\Plugins\Usage\Data\UsageSummary;

/**
 * In-memory usage tracker for development and testing.
 */
class InMemoryUsageTracker implements UsageTrackerContract
{
    /** @var array<int, UsageRecord> */
    protected array $records = [];

    /**
     * {@inheritdoc}
     */
    public function record(UsageRecord $record): void
    {
        $this->records[] = $record;
    }

    /**
     * {@inheritdoc}
     */
    public function getSummary(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        ?string $userId = null,
        ?string $model = null,
    ): UsageSummary {
        $filteredRecords = $this->filterRecords($start, $end, $userId, $model);

        return UsageSummary::fromRecords($filteredRecords, $start, $end);
    }

    /**
     * {@inheritdoc}
     */
    public function getRecords(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        ?string $userId = null,
        ?string $model = null,
        ?int $limit = null,
    ): array {
        $filtered = $this->filterRecords($start, $end, $userId, $model);

        if ($limit !== null) {
            return array_slice($filtered, 0, $limit);
        }

        return $filtered;
    }

    /**
     * {@inheritdoc}
     */
    public function getRecentRecords(int $limit = 10, ?string $userId = null): array
    {
        $records = $this->records;

        if ($userId !== null) {
            $records = array_filter($records, fn (UsageRecord $r) => $r->userId === $userId);
        }

        // Sort by timestamp descending
        usort($records, fn (UsageRecord $a, UsageRecord $b) => $b->timestamp <=> $a->timestamp);

        return array_slice($records, 0, $limit);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->records = [];
    }

    /**
     * Filter records by criteria.
     *
     * @return array<int, UsageRecord>
     */
    protected function filterRecords(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        ?string $userId = null,
        ?string $model = null,
    ): array {
        return array_values(array_filter(
            $this->records,
            function (UsageRecord $record) use ($start, $end, $userId, $model) {
                if ($record->timestamp < $start || $record->timestamp > $end) {
                    return false;
                }

                if ($userId !== null && $record->userId !== $userId) {
                    return false;
                }

                if ($model !== null && $record->model !== $model) {
                    return false;
                }

                return true;
            }
        ));
    }

    /**
     * Get all records (for testing).
     *
     * @return array<int, UsageRecord>
     */
    public function all(): array
    {
        return $this->records;
    }
}
