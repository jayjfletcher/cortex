<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow\Nodes;

use Closure;
use JayI\Cortex\Plugins\Workflow\Contracts\NodeContract;
use JayI\Cortex\Plugins\Workflow\NodeResult;
use JayI\Cortex\Plugins\Workflow\WorkflowState;

/**
 * A node that branches based on a condition.
 */
class ConditionNode implements NodeContract
{
    /**
     * @param  array<string, string|null>  $branches  Mapping of 'true' and 'false' to node IDs
     */
    public function __construct(
        protected string $nodeId,
        protected Closure $condition,
        protected array $branches = [],
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
        $result = ($this->condition)($input, $state);
        $branch = $result ? 'true' : 'false';

        $nextNode = $this->branches[$branch] ?? null;

        return NodeResult::success([
            '_next_node' => $nextNode,
            'condition_result' => $result,
            'branch' => $branch,
        ]);
    }
}
