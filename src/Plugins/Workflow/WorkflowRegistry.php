<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow;

use JayI\Cortex\Exceptions\WorkflowException;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowContract;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowRegistryContract;

/**
 * Default workflow registry implementation.
 */
class WorkflowRegistry implements WorkflowRegistryContract
{
    protected WorkflowCollection $workflows;

    public function __construct()
    {
        $this->workflows = WorkflowCollection::make([]);
    }

    /**
     * {@inheritdoc}
     */
    public function register(WorkflowContract $workflow): void
    {
        $this->workflows = $this->workflows->add($workflow);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $id): WorkflowContract
    {
        if (! $this->has($id)) {
            throw WorkflowException::workflowNotFound($id);
        }

        return $this->workflows->get($id);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool
    {
        return $this->workflows->has($id);
    }

    /**
     * {@inheritdoc}
     */
    public function all(): WorkflowCollection
    {
        return $this->workflows;
    }

    /**
     * {@inheritdoc}
     */
    public function only(array $ids): WorkflowCollection
    {
        return $this->workflows->only($ids);
    }

    /**
     * {@inheritdoc}
     */
    public function except(array $ids): WorkflowCollection
    {
        return $this->workflows->except($ids);
    }

    /**
     * Remove a workflow by ID.
     */
    public function remove(string $id): void
    {
        $this->workflows = $this->workflows->remove($id);
    }
}
