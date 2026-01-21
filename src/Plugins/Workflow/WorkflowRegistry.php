<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow;

use Illuminate\Support\Collection;
use JayI\Cortex\Exceptions\WorkflowException;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowContract;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowRegistryContract;

/**
 * Default workflow registry implementation.
 */
class WorkflowRegistry implements WorkflowRegistryContract
{
    /** @var array<string, WorkflowContract> */
    protected array $workflows = [];

    /**
     * {@inheritdoc}
     */
    public function register(WorkflowContract $workflow): void
    {
        $this->workflows[$workflow->id()] = $workflow;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $id): WorkflowContract
    {
        if (! isset($this->workflows[$id])) {
            throw WorkflowException::workflowNotFound($id);
        }

        return $this->workflows[$id];
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool
    {
        return isset($this->workflows[$id]);
    }

    /**
     * {@inheritdoc}
     */
    public function all(): Collection
    {
        return collect($this->workflows);
    }

    /**
     * Remove a workflow by ID.
     */
    public function remove(string $id): void
    {
        unset($this->workflows[$id]);
    }
}
