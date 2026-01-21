<?php

declare(strict_types=1);

namespace JayI\Cortex\Events\Agent;

use JayI\Cortex\Events\CortexEvent;
use JayI\Cortex\Plugins\Agent\Contracts\AgentContract;

class AgentMaxIterationsReached extends CortexEvent
{
    public function __construct(
        public readonly AgentContract $agent,
        public readonly array $state,
        public readonly int $maxIterations,
        ?string $tenantId = null,
        ?string $correlationId = null,
        array $metadata = [],
    ) {
        parent::__construct($tenantId, $correlationId, $metadata);
    }
}
