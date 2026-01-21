<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow\Contracts;

use Illuminate\Support\Collection;
use JayI\Cortex\Plugins\Workflow\WorkflowState;
use JayI\Cortex\Plugins\Workflow\WorkflowStatus;

interface WorkflowStateRepositoryContract
{
    /**
     * Save a workflow state.
     */
    public function save(WorkflowState $state): void;

    /**
     * Find a workflow state by run ID.
     */
    public function find(string $runId): ?WorkflowState;

    /**
     * Find all states for a workflow.
     *
     * @return Collection<int, WorkflowState>
     */
    public function findByWorkflow(string $workflowId): Collection;

    /**
     * Find all states with a specific status.
     *
     * @return Collection<int, WorkflowState>
     */
    public function findByStatus(WorkflowStatus $status): Collection;

    /**
     * Delete a workflow state by run ID.
     */
    public function delete(string $runId): void;

    /**
     * Delete expired completed/failed states.
     *
     * @return int Number of deleted states
     */
    public function deleteExpired(): int;
}
