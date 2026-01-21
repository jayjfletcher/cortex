<?php

declare(strict_types=1);

namespace JayI\Cortex\Events\Workflow;

use JayI\Cortex\Events\CortexEvent;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowContract;

class WorkflowStarted extends CortexEvent
{
    public function __construct(
        public readonly WorkflowContract $workflow,
        public readonly array $input,
        public readonly string $runId,
        ?string $tenantId = null,
        ?string $correlationId = null,
        array $metadata = [],
    ) {
        parent::__construct($tenantId, $correlationId, $metadata);
    }
}
