<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow;

use JayI\Cortex\Events\Concerns\DispatchesCortexEvents;
use JayI\Cortex\Events\Workflow\WorkflowCompleted;
use JayI\Cortex\Events\Workflow\WorkflowFailed;
use JayI\Cortex\Events\Workflow\WorkflowNodeEntered;
use JayI\Cortex\Events\Workflow\WorkflowNodeExited;
use JayI\Cortex\Events\Workflow\WorkflowPaused;
use JayI\Cortex\Events\Workflow\WorkflowResumed;
use JayI\Cortex\Events\Workflow\WorkflowStarted;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowContract;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowExecutorContract;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowStateRepositoryContract;
use JayI\Cortex\Plugins\Workflow\Exceptions\WorkflowNotFoundException;
use JayI\Cortex\Plugins\Workflow\Exceptions\WorkflowNotPausedException;

/**
 * Workflow executor with automatic state persistence.
 */
class PersistentWorkflowExecutor implements WorkflowExecutorContract
{
    use DispatchesCortexEvents;

    public function __construct(
        protected WorkflowExecutor $baseExecutor,
        protected WorkflowStateRepositoryContract $repository,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function execute(WorkflowContract $workflow, array $input = [], ?WorkflowContext $context = null): WorkflowResult
    {
        $definition = $workflow->definition();
        $context ??= new WorkflowContext();

        // Create initial state
        $state = WorkflowState::start(
            $definition->id,
            $context->correlationId ?? uniqid('run_'),
            $definition->entryNode ?? '',
        )->merge($input);

        // Persist initial state
        $this->repository->save($state);

        // Dispatch start event
        $this->dispatchCortexEvent(new WorkflowStarted(
            workflow: $workflow,
            input: $input,
            runId: $state->runId,
        ));

        // Execute with persistence
        return $this->executeWithPersistence($workflow, $state, $input);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(WorkflowContract $workflow, WorkflowState $state, array $input = []): WorkflowResult
    {
        if (! $state->status->canResume()) {
            throw WorkflowNotPausedException::withStatus($state->runId, $state->status);
        }

        // Resume the state
        $state = $state->resume()->merge($input);

        // Persist resumed state
        $this->repository->save($state);

        // Dispatch resume event
        $this->dispatchCortexEvent(new WorkflowResumed(
            workflow: $workflow,
            state: $state,
            input: $input,
        ));

        // Execute with persistence
        return $this->executeWithPersistence($workflow, $state, $input);
    }

    /**
     * Resume a workflow by run ID.
     *
     * @param  array<string, mixed>  $input
     */
    public function resumeByRunId(WorkflowContract $workflow, string $runId, array $input = []): WorkflowResult
    {
        $state = $this->repository->find($runId);

        if ($state === null) {
            throw WorkflowNotFoundException::forRunId($runId);
        }

        return $this->resume($workflow, $state, $input);
    }

    /**
     * Get the state for a run.
     */
    public function getState(string $runId): ?WorkflowState
    {
        return $this->repository->find($runId);
    }

    /**
     * Execute the workflow with automatic state persistence.
     *
     * @param  array<string, mixed>  $input
     */
    protected function executeWithPersistence(WorkflowContract $workflow, WorkflowState $state, array $input): WorkflowResult
    {
        // Execute using the base executor
        $result = $this->baseExecutor->resume($workflow, $state, $input);

        // Persist final state
        $this->repository->save($result->state);

        // Dispatch appropriate event
        $this->dispatchResultEvent($workflow, $result);

        return $result;
    }

    /**
     * Dispatch the appropriate event based on result status.
     */
    protected function dispatchResultEvent(WorkflowContract $workflow, WorkflowResult $result): void
    {
        match ($result->state->status) {
            WorkflowStatus::Completed => $this->dispatchCortexEvent(new WorkflowCompleted(
                workflow: $workflow,
                input: $result->state->data,
                output: $result->output(),
                runId: $result->state->runId,
            )),
            WorkflowStatus::Failed => $this->dispatchCortexEvent(new WorkflowFailed(
                workflow: $workflow,
                state: $result->state,
                exception: new \RuntimeException($result->error ?? 'Workflow failed'),
            )),
            WorkflowStatus::Paused => $this->dispatchCortexEvent(new WorkflowPaused(
                workflow: $workflow,
                state: $result->state,
                reason: $result->error,
            )),
            default => null,
        };
    }

    /**
     * Set maximum steps on the base executor.
     */
    public function maxSteps(int $maxSteps): static
    {
        $this->baseExecutor->maxSteps($maxSteps);

        return $this;
    }
}
