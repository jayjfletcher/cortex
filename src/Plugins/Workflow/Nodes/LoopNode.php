<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow\Nodes;

use Closure;
use JayI\Cortex\Plugins\Workflow\Contracts\NodeContract;
use JayI\Cortex\Plugins\Workflow\NodeResult;
use JayI\Cortex\Plugins\Workflow\WorkflowState;

/**
 * A node that loops while a condition is true.
 */
class LoopNode implements NodeContract
{
    public function __construct(
        protected string $nodeId,
        protected NodeContract $body,
        protected Closure $condition,
        protected int $maxIterations = 100,
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
        $iteration = 0;
        $currentInput = $input;
        $allOutputs = [];

        while ($iteration < $this->maxIterations) {
            // Check condition
            if (! ($this->condition)($currentInput, $state, $iteration)) {
                break;
            }

            // Execute body
            $result = $this->body->execute($currentInput, $state);

            if (! $result->success) {
                return NodeResult::failure("Loop iteration {$iteration} failed: {$result->error}");
            }

            if ($result->shouldPause) {
                return $result;
            }

            $allOutputs[] = $result->output;
            $currentInput = array_merge($currentInput, $result->output);
            $state = $state->merge($result->output);

            $iteration++;
        }

        return NodeResult::success([
            'iterations' => $iteration,
            'outputs' => $allOutputs,
            'final_output' => $currentInput,
        ]);
    }
}
