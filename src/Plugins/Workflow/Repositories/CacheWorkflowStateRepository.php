<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowStateRepositoryContract;
use JayI\Cortex\Plugins\Workflow\WorkflowState;
use JayI\Cortex\Plugins\Workflow\WorkflowStatus;

class CacheWorkflowStateRepository implements WorkflowStateRepositoryContract
{
    protected function ttl(): int
    {
        return Config::get('cortex.workflow.persistence.ttl', 86400 * 7);
    }

    protected function key(string $runId): string
    {
        return "cortex:workflow_state:{$runId}";
    }

    protected function indexKey(string $workflowId): string
    {
        return "cortex:workflow_index:{$workflowId}";
    }

    protected function statusIndexKey(string $status): string
    {
        return "cortex:workflow_status_index:{$status}";
    }

    public function save(WorkflowState $state): void
    {
        Cache::put($this->key($state->runId), $state, $this->ttl());

        // Maintain index of runs per workflow
        $workflowIndex = Cache::get($this->indexKey($state->workflowId), []);
        $workflowIndex[$state->runId] = time();
        Cache::put($this->indexKey($state->workflowId), $workflowIndex, $this->ttl());

        // Maintain status index
        $statusIndex = Cache::get($this->statusIndexKey($state->status->value), []);
        $statusIndex[$state->runId] = time();
        Cache::put($this->statusIndexKey($state->status->value), $statusIndex, $this->ttl());
    }

    public function find(string $runId): ?WorkflowState
    {
        return Cache::get($this->key($runId));
    }

    public function findByWorkflow(string $workflowId): Collection
    {
        $index = Cache::get($this->indexKey($workflowId), []);

        return collect(array_keys($index))
            ->map(fn ($runId) => $this->find($runId))
            ->filter()
            ->sortByDesc(fn ($state) => $state->startedAt?->getTimestamp() ?? 0)
            ->values();
    }

    public function findByStatus(WorkflowStatus $status): Collection
    {
        $index = Cache::get($this->statusIndexKey($status->value), []);

        return collect(array_keys($index))
            ->map(fn ($runId) => $this->find($runId))
            ->filter()
            ->filter(fn ($state) => $state->status === $status)
            ->sortByDesc(fn ($state) => $state->startedAt?->getTimestamp() ?? 0)
            ->values();
    }

    public function delete(string $runId): void
    {
        $state = $this->find($runId);

        if ($state) {
            // Remove from workflow index
            $workflowIndex = Cache::get($this->indexKey($state->workflowId), []);
            unset($workflowIndex[$runId]);
            Cache::put($this->indexKey($state->workflowId), $workflowIndex, $this->ttl());

            // Remove from status index
            $statusIndex = Cache::get($this->statusIndexKey($state->status->value), []);
            unset($statusIndex[$runId]);
            Cache::put($this->statusIndexKey($state->status->value), $statusIndex, $this->ttl());
        }

        Cache::forget($this->key($runId));
    }

    public function deleteExpired(): int
    {
        // Cache handles TTL automatically, but we can clean up indexes
        $count = 0;
        $ttl = $this->ttl();
        $cutoff = time() - $ttl;

        foreach ([WorkflowStatus::Completed, WorkflowStatus::Failed, WorkflowStatus::Cancelled] as $status) {
            $statusIndex = Cache::get($this->statusIndexKey($status->value), []);
            $toDelete = [];

            foreach ($statusIndex as $runId => $timestamp) {
                if ($timestamp < $cutoff) {
                    $toDelete[] = $runId;
                }
            }

            foreach ($toDelete as $runId) {
                $this->delete($runId);
                $count++;
            }
        }

        return $count;
    }
}
