<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow\Contracts;

use JayI\Cortex\Plugins\Workflow\WorkflowCollection;

interface WorkflowRegistryContract
{
    /**
     * Register a workflow.
     */
    public function register(WorkflowContract $workflow): void;

    /**
     * Get a workflow by ID.
     */
    public function get(string $id): WorkflowContract;

    /**
     * Check if a workflow exists.
     */
    public function has(string $id): bool;

    /**
     * Get all registered workflows.
     */
    public function all(): WorkflowCollection;

    /**
     * Get only the specified workflows.
     *
     * @param  array<int, string>  $ids
     */
    public function only(array $ids): WorkflowCollection;

    /**
     * Get all workflows except the specified ones.
     *
     * @param  array<int, string>  $ids
     */
    public function except(array $ids): WorkflowCollection;
}
