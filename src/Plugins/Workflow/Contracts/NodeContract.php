<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow\Contracts;

use JayI\Cortex\Plugins\Workflow\NodeResult;
use JayI\Cortex\Plugins\Workflow\WorkflowState;

interface NodeContract
{
    /**
     * Get the node's unique identifier.
     */
    public function id(): string;

    /**
     * Execute the node.
     *
     * @param  array<string, mixed>  $input
     */
    public function execute(array $input, WorkflowState $state): NodeResult;
}
