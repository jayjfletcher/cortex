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
use JayI\Cortex\Exceptions\WorkflowException;
use JayI\Cortex\Plugins\Workflow\Contracts\NodeContract;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowContract;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowExecutorContract;

/**
 * Default workflow executor implementation.
 */
class WorkflowExecutor implements WorkflowExecutorContract
{
    use DispatchesCortexEvents;

    protected int $maxSteps = 1000;

    /**
     * Set the maximum number of execution steps.
     */
    public function maxSteps(int $maxSteps): static
    {
        $this->maxSteps = $maxSteps;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(WorkflowContract $workflow, array $input = [], ?WorkflowContext $context = null): WorkflowResult
    {
        $definition = $workflow->definition();
        $context ??= new WorkflowContext;

        // Create initial state
        $state = WorkflowState::start(
            $definition->id,
            $context->correlationId ?? uniqid('run_'),
            $definition->entryNode ?? '',
        )->merge($input);

        $this->dispatchCortexEvent(new WorkflowStarted(
            workflow: $workflow,
            input: $input,
            runId: $state->runId,
        ));

        return $this->executeFromState($workflow, $state, $input);
    }

    /**
     * {@inheritdoc}
     */
    public function resume(WorkflowContract $workflow, WorkflowState $state, array $input = []): WorkflowResult
    {
        if (! $state->status->canResume()) {
            throw WorkflowException::invalidState($state->status->value);
        }

        // Merge resume input with existing data and set status to running
        $state = $state->resume()->merge($input);

        $this->dispatchCortexEvent(new WorkflowResumed(
            workflow: $workflow,
            state: $state,
            input: $input,
        ));

        return $this->executeFromState($workflow, $state, $input);
    }

    /**
     * Execute the workflow from a given state.
     *
     * @param  array<string, mixed>  $input
     */
    protected function executeFromState(WorkflowContract $workflow, WorkflowState $state, array $input): WorkflowResult
    {
        $steps = 0;
        $definition = $workflow->definition();
        $originalInput = $input;

        // Build node lookup
        $nodes = [];
        foreach ($definition->nodes as $node) {
            $nodes[$node->id()] = $node;
        }

        // Build edge lookup
        $edges = [];
        foreach ($definition->edges as $edge) {
            $edges[$edge->from][] = $edge;
        }

        while ($steps < $this->maxSteps) {
            $steps++;

            // Check if we have a current node
            if ($state->currentNode === null || $state->currentNode === '') {
                // No more nodes to execute - workflow completed
                $state = $state->complete();

                $this->dispatchCortexEvent(new WorkflowCompleted(
                    workflow: $workflow,
                    input: $originalInput,
                    output: $state->data,
                    runId: $state->runId,
                ));

                return WorkflowResult::completed($state);
            }

            // Get current node
            $node = $nodes[$state->currentNode] ?? null;
            if ($node === null) {
                throw WorkflowException::nodeNotFound($state->currentNode);
            }

            $this->dispatchCortexEvent(new WorkflowNodeEntered(
                workflow: $workflow,
                node: $node->id(),
                state: $state,
            ));

            // Execute node
            $startTime = microtime(true);
            try {
                $result = $node->execute($input, $state);
            } catch (\Throwable $e) {
                $duration = microtime(true) - $startTime;
                $state = $state
                    ->recordNodeExecution($node->id(), $input, [], $duration, $e->getMessage())
                    ->fail();

                $this->dispatchCortexEvent(new WorkflowFailed(
                    workflow: $workflow,
                    state: $state,
                    exception: $e,
                ));

                return WorkflowResult::failed($state, $e->getMessage());
            }

            $duration = microtime(true) - $startTime;

            // Record execution
            $state = $state->recordNodeExecution(
                $node->id(),
                $input,
                $result->output,
                $duration,
                $result->success ? null : $result->error
            );

            $this->dispatchCortexEvent(new WorkflowNodeExited(
                workflow: $workflow,
                node: $node->id(),
                state: $state,
                output: $result->output,
            ));

            // Handle result
            if (! $result->success) {
                $state = $state->fail();

                $this->dispatchCortexEvent(new WorkflowFailed(
                    workflow: $workflow,
                    state: $state,
                    exception: new \RuntimeException($result->error ?? 'Unknown error'),
                ));

                return WorkflowResult::failed($state, $result->error);
            }

            if ($result->shouldPause) {
                $state = $state->pause($result->pauseReason ?? 'Paused');

                $this->dispatchCortexEvent(new WorkflowPaused(
                    workflow: $workflow,
                    state: $state,
                    reason: $result->pauseReason,
                ));

                return WorkflowResult::paused($state, $result->pauseReason ?? 'Paused');
            }

            // Merge output into state
            $state = $state->merge($result->output);
            $input = array_merge($input, $result->output);

            // Determine next node
            $nextNode = $this->determineNextNode($node, $edges, $input, $state, $result);
            $state = $state->moveTo($nextNode);
        }

        // Max steps exceeded
        $state = $state->fail();

        $this->dispatchCortexEvent(new WorkflowFailed(
            workflow: $workflow,
            state: $state,
            exception: new \RuntimeException("Maximum steps ({$this->maxSteps}) exceeded"),
        ));

        return WorkflowResult::failed($state, "Maximum steps ({$this->maxSteps}) exceeded");
    }

    /**
     * Determine the next node to execute.
     *
     * @param  array<string, array<Edge>>  $edges
     * @param  array<string, mixed>  $input
     */
    protected function determineNextNode(
        NodeContract $currentNode,
        array $edges,
        array $input,
        WorkflowState $state,
        NodeResult $result
    ): ?string {
        // Check if the result specifies a next node
        if ($result->nextNode !== null) {
            return $result->nextNode;
        }

        // Check if the result specifies a next node via output (for condition nodes)
        if (isset($result->output['_next_node'])) {
            return $result->output['_next_node'];
        }

        // Get edges from current node
        $nodeEdges = $edges[$currentNode->id()] ?? [];

        if (empty($nodeEdges)) {
            // No outgoing edges - workflow ends
            return null;
        }

        // Sort by priority (higher first)
        usort($nodeEdges, fn (Edge $a, Edge $b) => $b->priority <=> $a->priority);

        // Find first matching edge
        foreach ($nodeEdges as $edge) {
            if ($edge->condition === null || ($edge->condition)($input, $state)) {
                return $edge->to;
            }
        }

        // No matching edge found
        return null;
    }
}
