<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow;

use Spatie\LaravelData\Data;

class WorkflowResult extends Data
{
    public function __construct(
        public WorkflowState $state,
        public bool $completed,
        public bool $paused,
        public ?string $pauseReason = null,
        public ?string $error = null,
    ) {}

    /**
     * Create a completed result.
     */
    public static function completed(WorkflowState $state): static
    {
        return new static(
            state: $state,
            completed: true,
            paused: false,
        );
    }

    /**
     * Create a paused result.
     */
    public static function paused(WorkflowState $state, string $reason): static
    {
        return new static(
            state: $state,
            completed: false,
            paused: true,
            pauseReason: $reason,
        );
    }

    /**
     * Create a failed result.
     */
    public static function failed(WorkflowState $state, ?string $error = null): static
    {
        return new static(
            state: $state,
            completed: false,
            paused: false,
            error: $error,
        );
    }

    /**
     * Get a value from the final state data.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->state->get($key, $default);
    }

    /**
     * Get all output data.
     *
     * @return array<string, mixed>
     */
    public function output(): array
    {
        return $this->state->data;
    }

    /**
     * Check if the workflow succeeded.
     */
    public function isSuccess(): bool
    {
        return $this->completed && $this->state->status === WorkflowStatus::Completed;
    }

    /**
     * Check if the workflow failed.
     */
    public function isFailed(): bool
    {
        return $this->state->status === WorkflowStatus::Failed;
    }

    /**
     * Check if the workflow is completed.
     */
    public function isCompleted(): bool
    {
        return $this->completed && $this->state->status === WorkflowStatus::Completed;
    }

    /**
     * Check if the workflow is paused.
     */
    public function isPaused(): bool
    {
        return $this->paused && $this->state->status === WorkflowStatus::Paused;
    }
}
