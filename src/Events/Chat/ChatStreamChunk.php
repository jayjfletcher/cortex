<?php

declare(strict_types=1);

namespace JayI\Cortex\Events\Chat;

use JayI\Cortex\Events\CortexEvent;
use JayI\Cortex\Plugins\Chat\StreamChunk;

class ChatStreamChunk extends CortexEvent
{
    public function __construct(
        public readonly StreamChunk $chunk,
        public readonly int $index,
        ?string $tenantId = null,
        ?string $correlationId = null,
        array $metadata = [],
    ) {
        parent::__construct($tenantId, $correlationId, $metadata);
    }
}
