<?php

declare(strict_types=1);

namespace JayI\Cortex\Events\Provider;

use JayI\Cortex\Events\CortexEvent;
use JayI\Cortex\Plugins\Chat\ChatRequest;

class BeforeProviderRequest extends CortexEvent
{
    public function __construct(
        public readonly string $provider,
        public readonly ChatRequest $request,
        public readonly string $model,
        ?string $tenantId = null,
        ?string $correlationId = null,
        array $metadata = [],
    ) {
        parent::__construct($tenantId, $correlationId, $metadata);
    }
}
