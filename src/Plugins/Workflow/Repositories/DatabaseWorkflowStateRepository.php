<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow\Repositories;

use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowStateRepositoryContract;
use JayI\Cortex\Plugins\Workflow\WorkflowHistoryEntry;
use JayI\Cortex\Plugins\Workflow\WorkflowState;
use JayI\Cortex\Plugins\Workflow\WorkflowStatus;

class DatabaseWorkflowStateRepository implements WorkflowStateRepositoryContract
{
    protected function table(): string
    {
        return Config::get('cortex.workflow.persistence.table', 'cortex_workflow_states');
    }

    public function save(WorkflowState $state): void
    {
        DB::table($this->table())->updateOrInsert(
            ['run_id' => $state->runId],
            [
                'workflow_id' => $state->workflowId,
                'current_node' => $state->currentNode,
                'status' => $state->status->value,
                'data' => json_encode($state->data),
                'history' => json_encode(array_map(fn ($h) => $h->toArray(), $state->history)),
                'pause_reason' => $state->pauseReason,
                'started_at' => $state->startedAt?->format('Y-m-d H:i:s'),
                'paused_at' => $state->pausedAt?->format('Y-m-d H:i:s'),
                'completed_at' => $state->completedAt?->format('Y-m-d H:i:s'),
                'updated_at' => now(),
            ]
        );
    }

    public function find(string $runId): ?WorkflowState
    {
        $row = DB::table($this->table())->where('run_id', $runId)->first();

        if (! $row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findByWorkflow(string $workflowId): Collection
    {
        return DB::table($this->table())
            ->where('workflow_id', $workflowId)
            ->orderByDesc('started_at')
            ->get()
            ->map(fn ($row) => $this->hydrate($row));
    }

    public function findByStatus(WorkflowStatus $status): Collection
    {
        return DB::table($this->table())
            ->where('status', $status->value)
            ->orderByDesc('started_at')
            ->get()
            ->map(fn ($row) => $this->hydrate($row));
    }

    public function delete(string $runId): void
    {
        DB::table($this->table())->where('run_id', $runId)->delete();
    }

    public function deleteExpired(): int
    {
        $ttl = Config::get('cortex.workflow.persistence.ttl', 86400 * 7);

        return DB::table($this->table())
            ->where('updated_at', '<', now()->subSeconds($ttl))
            ->whereIn('status', [
                WorkflowStatus::Completed->value,
                WorkflowStatus::Failed->value,
                WorkflowStatus::Cancelled->value,
            ])
            ->delete();
    }

    protected function hydrate(object $row): WorkflowState
    {
        $historyData = json_decode($row->history, true) ?? [];
        $history = array_map(
            fn ($h) => WorkflowHistoryEntry::from($h),
            $historyData
        );

        return new WorkflowState(
            workflowId: $row->workflow_id,
            runId: $row->run_id,
            currentNode: $row->current_node,
            status: WorkflowStatus::from($row->status),
            data: json_decode($row->data, true) ?? [],
            history: $history,
            pauseReason: $row->pause_reason,
            startedAt: $row->started_at ? new DateTimeImmutable($row->started_at) : null,
            pausedAt: $row->paused_at ? new DateTimeImmutable($row->paused_at) : null,
            completedAt: $row->completed_at ? new DateTimeImmutable($row->completed_at) : null,
        );
    }
}
