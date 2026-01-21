<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow\Contracts;

use Illuminate\Support\Collection;

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
     *
     * @return Collection<string, WorkflowContract>
     */
    public function all(): Collection;
}
