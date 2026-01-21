<?php

declare(strict_types=1);

namespace JayI\Cortex\Events\Guardrail;

use JayI\Cortex\Events\CortexEvent;
use JayI\Cortex\Plugins\Guardrail\Contracts\GuardrailContract;

class GuardrailBlocked extends CortexEvent
{
    public function __construct(
        public readonly GuardrailContract $guardrail,
        public readonly string $content,
        public readonly array $violations,
        ?string $tenantId = null,
        ?string $correlationId = null,
        array $metadata = [],
    ) {
        parent::__construct($tenantId, $correlationId, $metadata);
    }
}
