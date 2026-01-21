<?php

declare(strict_types=1);

namespace JayI\Cortex\Events\Agent;

use JayI\Cortex\Events\CortexEvent;
use JayI\Cortex\Plugins\Agent\Contracts\AgentContract;

class AgentRunStarted extends CortexEvent
{
    public function __construct(
        public readonly AgentContract $agent,
        public readonly string|array $input,
        ?string $tenantId = null,
        ?string $correlationId = null,
        array $metadata = [],
    ) {
        parent::__construct($tenantId, $correlationId, $metadata);
    }
}
