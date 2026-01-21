<?php

declare(strict_types=1);

namespace JayI\Cortex\Events\Chat;

use JayI\Cortex\Events\CortexEvent;
use JayI\Cortex\Plugins\Chat\ChatRequest;
use Throwable;

class ChatError extends CortexEvent
{
    public function __construct(
        public readonly ChatRequest $request,
        public readonly Throwable $exception,
        ?string $tenantId = null,
        ?string $correlationId = null,
        array $metadata = [],
    ) {
        parent::__construct($tenantId, $correlationId, $metadata);
    }
}
