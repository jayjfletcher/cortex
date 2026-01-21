<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow\Contracts;

use JayI\Cortex\Plugins\Workflow\WorkflowContext;
use JayI\Cortex\Plugins\Workflow\WorkflowResult;
use JayI\Cortex\Plugins\Workflow\WorkflowState;

interface WorkflowExecutorContract
{
    /**
     * Execute a workflow from the beginning.
     *
     * @param  array<string, mixed>  $input
     */
    public function execute(WorkflowContract $workflow, array $input, ?WorkflowContext $context = null): WorkflowResult;

    /**
     * Resume a paused workflow.
     *
     * @param  array<string, mixed>  $input
     */
    public function resume(WorkflowContract $workflow, WorkflowState $state, array $input = []): WorkflowResult;
}
