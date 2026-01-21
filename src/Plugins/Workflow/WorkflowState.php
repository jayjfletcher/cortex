<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow;

use DateTimeImmutable;
use DateTimeInterface;
use Spatie\LaravelData\Data;

class WorkflowState extends Data
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, WorkflowHistoryEntry>  $history
     */
    public function __construct(
        public string $workflowId,
        public string $runId,
        public ?string $currentNode = null,
        public WorkflowStatus $status = WorkflowStatus::Pending,
        public array $data = [],
        public array $history = [],
        public ?string $pauseReason = null,
        public ?DateTimeInterface $startedAt = null,
        public ?DateTimeInterface $pausedAt = null,
        public ?DateTimeInterface $completedAt = null,
    ) {}

    /**
     * Create a new state for a workflow run.
     */
    public static function start(string $workflowId, string $runId, string $entryPoint): static
    {
        return new static(
            workflowId: $workflowId,
            runId: $runId,
            currentNode: $entryPoint,
            status: WorkflowStatus::Running,
            startedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Get a value from the state data.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Set a value in the state data.
     */
    public function set(string $key, mixed $value): static
    {
        $data = $this->data;
        $data[$key] = $value;

        return new static(
            workflowId: $this->workflowId,
            runId: $this->runId,
            currentNode: $this->currentNode,
            status: $this->status,
            data: $data,
            history: $this->history,
            pauseReason: $this->pauseReason,
            startedAt: $this->startedAt,
            pausedAt: $this->pausedAt,
            completedAt: $this->completedAt,
        );
    }

    /**
     * Check if a key exists in the state data.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Merge data into the state.
     *
     * @param  array<string, mixed>  $data
     */
    public function merge(array $data): static
    {
        return new static(
            workflowId: $this->workflowId,
            runId: $this->runId,
            currentNode: $this->currentNode,
            status: $this->status,
            data: array_merge($this->data, $data),
            history: $this->history,
            pauseReason: $this->pauseReason,
            startedAt: $this->startedAt,
            pausedAt: $this->pausedAt,
            completedAt: $this->completedAt,
        );
    }

    /**
     * Move to a new node.
     */
    public function moveTo(?string $nodeId): static
    {
        return new static(
            workflowId: $this->workflowId,
            runId: $this->runId,
            currentNode: $nodeId,
            status: $this->status,
            data: $this->data,
            history: $this->history,
            pauseReason: $this->pauseReason,
            startedAt: $this->startedAt,
            pausedAt: $this->pausedAt,
            completedAt: $this->completedAt,
        );
    }

    /**
     * Record a node execution in history.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $output
     */
    public function recordNodeExecution(
        string $nodeId,
        array $input,
        array $output,
        float $duration,
        ?string $error = null
    ): static {
        $entry = $error !== null
            ? WorkflowHistoryEntry::failure($nodeId, $input, $error, $duration)
            : WorkflowHistoryEntry::success($nodeId, $input, $output, $duration);

        return $this->addHistory($entry);
    }

    /**
     * Add a history entry.
     */
    public function addHistory(WorkflowHistoryEntry $entry): static
    {
        $history = $this->history;
        $history[] = $entry;

        return new static(
            workflowId: $this->workflowId,
            runId: $this->runId,
            currentNode: $this->currentNode,
            status: $this->status,
            data: $this->data,
            history: $history,
            pauseReason: $this->pauseReason,
            startedAt: $this->startedAt,
            pausedAt: $this->pausedAt,
            completedAt: $this->completedAt,
        );
    }

    /**
     * Pause the workflow.
     */
    public function pause(string $reason): static
    {
        return new static(
            workflowId: $this->workflowId,
            runId: $this->runId,
            currentNode: $this->currentNode,
            status: WorkflowStatus::Paused,
            data: $this->data,
            history: $this->history,
            pauseReason: $reason,
            startedAt: $this->startedAt,
            pausedAt: new DateTimeImmutable(),
            completedAt: $this->completedAt,
        );
    }

    /**
     * Resume the workflow.
     */
    public function resume(): static
    {
        return new static(
            workflowId: $this->workflowId,
            runId: $this->runId,
            currentNode: $this->currentNode,
            status: WorkflowStatus::Running,
            data: $this->data,
            history: $this->history,
            pauseReason: null,
            startedAt: $this->startedAt,
            pausedAt: null,
            completedAt: $this->completedAt,
        );
    }

    /**
     * Complete the workflow.
     */
    public function complete(): static
    {
        return new static(
            workflowId: $this->workflowId,
            runId: $this->runId,
            currentNode: $this->currentNode,
            status: WorkflowStatus::Completed,
            data: $this->data,
            history: $this->history,
            pauseReason: null,
            startedAt: $this->startedAt,
            pausedAt: null,
            completedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Fail the workflow.
     */
    public function fail(): static
    {
        return new static(
            workflowId: $this->workflowId,
            runId: $this->runId,
            currentNode: $this->currentNode,
            status: WorkflowStatus::Failed,
            data: $this->data,
            history: $this->history,
            pauseReason: null,
            startedAt: $this->startedAt,
            pausedAt: null,
            completedAt: new DateTimeImmutable(),
        );
    }
}
