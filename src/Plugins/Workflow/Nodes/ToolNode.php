<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow\Nodes;

use Closure;
use JayI\Cortex\Plugins\Tool\Contracts\ToolContract;
use JayI\Cortex\Plugins\Tool\Contracts\ToolRegistryContract;
use JayI\Cortex\Plugins\Tool\ToolContext;
use JayI\Cortex\Plugins\Workflow\Contracts\NodeContract;
use JayI\Cortex\Plugins\Workflow\NodeResult;
use JayI\Cortex\Plugins\Workflow\WorkflowState;

/**
 * A node that executes a tool.
 */
class ToolNode implements NodeContract
{
    /**
     * @param  array<string, mixed>|Closure  $inputMapping
     */
    public function __construct(
        protected string $nodeId,
        protected ToolContract|string $tool,
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
        $tool = $this->resolveTool();

        // Resolve input mapping
        $toolInput = $this->resolveInput($input, $state);

        // Create tool context
        $context = new ToolContext(
            conversationId: $state->runId,
            metadata: [
                'workflow_id' => $state->workflowId,
                'node_id' => $this->nodeId,
            ],
        );

        try {
            $result = $tool->execute($toolInput, $context);

            if (! $result->success) {
                return NodeResult::failure($result->error ?? 'Tool execution failed');
            }

            $output = is_array($result->output) ? $result->output : ['result' => $result->output];

            // Store in specific key if configured
            if ($this->outputKey !== null) {
                $output = [$this->outputKey => $result->output];
            }

            return NodeResult::success($output);
        } catch (\Throwable $e) {
            return NodeResult::failure("Tool execution failed: {$e->getMessage()}");
        }
    }

    /**
     * Resolve the tool instance.
     */
    protected function resolveTool(): ToolContract
    {
        if ($this->tool instanceof ToolContract) {
            return $this->tool;
        }

        /** @var ToolRegistryContract $registry */
        $registry = app(ToolRegistryContract::class);

        return $registry->get($this->tool);
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

        // Static mapping - resolve values from state
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
