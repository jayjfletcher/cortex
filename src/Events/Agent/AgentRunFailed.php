<?php

declare(strict_types=1);

namespace JayI\Cortex\Events\Agent;

use JayI\Cortex\Events\CortexEvent;
use JayI\Cortex\Plugins\Agent\Contracts\AgentContract;
use Throwable;

class AgentRunFailed extends CortexEvent
{
    public function __construct(
        public readonly AgentContract|string $agent,
        public readonly string|array $input,
        public readonly Throwable $exception,
        public readonly int $iterations,
        ?string $tenantId = null,
        ?string $correlationId = null,
        array $metadata = [],
    ) {
        parent::__construct($tenantId, $correlationId, $metadata);
    }
}
