<?php

declare(strict_types=1);

namespace JayI\Cortex\Events\Agent;

use JayI\Cortex\Events\CortexEvent;
use JayI\Cortex\Plugins\Agent\Contracts\AgentContract;
use JayI\Cortex\Plugins\Chat\ChatResponse;

class AgentIterationCompleted extends CortexEvent
{
    public function __construct(
        public readonly AgentContract $agent,
        public readonly int $iteration,
        public readonly array $state,
        public readonly ChatResponse $response,
        ?string $tenantId = null,
        ?string $correlationId = null,
        array $metadata = [],
    ) {
        parent::__construct($tenantId, $correlationId, $metadata);
    }
}
