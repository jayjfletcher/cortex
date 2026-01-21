<?php

declare(strict_types=1);

namespace JayI\Cortex\Events\Tool;

use JayI\Cortex\Events\CortexEvent;
use JayI\Cortex\Plugins\Tool\Contracts\ToolContract;
use Throwable;

class ToolError extends CortexEvent
{
    public function __construct(
        public readonly ToolContract $tool,
        public readonly array $input,
        public readonly Throwable $exception,
        ?string $tenantId = null,
        ?string $correlationId = null,
        array $metadata = [],
    ) {
        parent::__construct($tenantId, $correlationId, $metadata);
    }
}
