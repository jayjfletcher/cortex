<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow\Contracts;

use JayI\Cortex\Plugins\Workflow\WorkflowContext;
use JayI\Cortex\Plugins\Workflow\WorkflowDefinition;
use JayI\Cortex\Plugins\Workflow\WorkflowResult;

interface WorkflowContract
{
    /**
     * Get the workflow's unique identifier.
     */
    public function id(): string;

    /**
     * Get the workflow's name.
     */
    public function name(): string;

    /**
     * Get the workflow's description.
     */
    public function description(): string;

    /**
     * Build and return the workflow definition.
     */
    public function definition(): WorkflowDefinition;

    /**
     * Run the workflow.
     *
     * @param  array<string, mixed>  $input
     */
    public function run(array $input, ?WorkflowContext $context = null): WorkflowResult;
}
