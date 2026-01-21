<?php

declare(strict_types=1);

namespace JayI\Cortex\Events\Guardrail;

use JayI\Cortex\Events\CortexEvent;
use JayI\Cortex\Plugins\Guardrail\Contracts\GuardrailContract;
use JayI\Cortex\Plugins\Guardrail\Data\GuardrailResult;

class GuardrailChecked extends CortexEvent
{
    public function __construct(
        public readonly GuardrailContract $guardrail,
        public readonly string $content,
        public readonly GuardrailResult $result,
        ?string $tenantId = null,
        ?string $correlationId = null,
        array $metadata = [],
    ) {
        parent::__construct($tenantId, $correlationId, $metadata);
    }
}
