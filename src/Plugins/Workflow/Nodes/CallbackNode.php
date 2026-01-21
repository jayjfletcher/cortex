<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow\Nodes;

use Closure;
use JayI\Cortex\Plugins\Workflow\Contracts\NodeContract;
use JayI\Cortex\Plugins\Workflow\NodeResult;
use JayI\Cortex\Plugins\Workflow\WorkflowState;

/**
 * A node that executes a callback function.
 */
class CallbackNode implements NodeContract
{
    public function __construct(
        protected string $nodeId,
        protected Closure $callback,
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
        try {
            $result = ($this->callback)($input, $state);

            if ($result instanceof NodeResult) {
                return $result;
            }

            if (is_array($result)) {
                return NodeResult::success($result);
            }

            return NodeResult::success(['result' => $result]);
        } catch (\Throwable $e) {
            return NodeResult::failure("Callback failed: {$e->getMessage()}");
        }
    }
}
