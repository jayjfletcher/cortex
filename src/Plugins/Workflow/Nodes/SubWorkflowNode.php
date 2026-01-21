<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow\Nodes;

use Closure;
use JayI\Cortex\Plugins\Workflow\Contracts\NodeContract;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowContract;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowRegistryContract;
use JayI\Cortex\Plugins\Workflow\NodeResult;
use JayI\Cortex\Plugins\Workflow\WorkflowContext;
use JayI\Cortex\Plugins\Workflow\WorkflowState;

/**
 * A node that executes a sub-workflow.
 */
class SubWorkflowNode implements NodeContract
{
    /**
     * @param  array<string, mixed>|Closure  $inputMapping
     */
    public function __construct(
        protected string $nodeId,
        protected WorkflowContract|string $workflow,
        protected array|Closure $inputMapping,
        protected ?string $outputKey = null,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return $this->nodeId;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(array $input, WorkflowState $state): NodeResult
    {
        $workflow = $this->resolveWorkflow();

        // Resolve input mapping
        $workflowInput = $this->resolveInput($input, $state);

        // Create context for sub-workflow
        $context = new WorkflowContext(
            correlationId: $state->runId,
            metadata: [
                'parent_workflow' => $state->workflowId,
                'parent_node' => $this->nodeId,
            ],
        );

        try {
            $result = $workflow->run($workflowInput, $context);

            if ($result->isFailed()) {
                return NodeResult::failure('Sub-workflow failed');
            }

            if ($result->paused) {
                return NodeResult::pause(
                    "Sub-workflow paused: {$result->pauseReason}",
                    ['sub_workflow_state' => $result->state]
                );
            }

            $output = $result->output();

            // Store in specific key if configured
            if ($this->outputKey !== null) {
                $output = [$this->outputKey => $output];
            }

            return NodeResult::success($output);
        } catch (\Throwable $e) {
            return NodeResult::failure("Sub-workflow failed: {$e->getMessage()}");
        }
    }

    /**
     * Resolve the workflow instance.
     */
    protected function resolveWorkflow(): WorkflowContract
    {
        if ($this->workflow instanceof WorkflowContract) {
            return $this->workflow;
        }

        /** @var WorkflowRegistryContract $registry */
        $registry = app(WorkflowRegistryContract::class);

        return $registry->get($this->workflow);
    }

    /**
     * Resolve input from mapping.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function resolveInput(array $input, WorkflowState $state): array
    {
        if ($this->inputMapping instanceof Closure) {
            return ($this->inputMapping)($input, $state);
        }

        // Static mapping
        $resolved = [];
        foreach ($this->inputMapping as $key => $value) {
            if (is_string($value) && str_starts_with($value, '$state.')) {
                $stateKey = substr($value, 7);
                $resolved[$key] = $state->get($stateKey);
            } elseif (is_string($value) && str_starts_with($value, '$input.')) {
                $inputKey = substr($value, 7);
                $resolved[$key] = $input[$inputKey] ?? null;
            } else {
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }
}
