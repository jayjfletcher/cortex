<?php

declare(strict_types=1);

namespace JayI\Cortex\Events\Workflow;

use JayI\Cortex\Events\CortexEvent;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowContract;
use JayI\Cortex\Plugins\Workflow\WorkflowState;

class WorkflowNodeExited extends CortexEvent
{
    public function __construct(
        public readonly WorkflowContract $workflow,
        public readonly string $node,
        public readonly WorkflowState $state,
        public readonly mixed $output,
        ?string $tenantId = null,
        ?string $correlationId = null,
        array $metadata = [],
    ) {
        parent::__construct($tenantId, $correlationId, $metadata);
    }
}
