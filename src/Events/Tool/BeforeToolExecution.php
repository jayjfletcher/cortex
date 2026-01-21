<?php

declare(strict_types=1);

namespace JayI\Cortex\Events\Tool;

use JayI\Cortex\Events\CortexEvent;
use JayI\Cortex\Plugins\Tool\Contracts\ToolContract;

class BeforeToolExecution extends CortexEvent
{
    public function __construct(
        public readonly ToolContract $tool,
        public readonly array $input,
        public readonly array $context = [],
        ?string $tenantId = null,
        ?string $correlationId = null,
        array $metadata = [],
    ) {
        parent::__construct($tenantId, $correlationId, $metadata);
    }
}
