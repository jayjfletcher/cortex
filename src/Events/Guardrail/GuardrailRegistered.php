<?php

declare(strict_types=1);

namespace JayI\Cortex\Events\Guardrail;

use JayI\Cortex\Events\CortexEvent;
use JayI\Cortex\Plugins\Guardrail\Contracts\GuardrailContract;

class GuardrailRegistered extends CortexEvent
{
    public function __construct(
        public readonly GuardrailContract $guardrail,
        ?string $tenantId = null,
        ?string $correlationId = null,
        array $metadata = [],
    ) {
        parent::__construct($tenantId, $correlationId, $metadata);
    }
}
