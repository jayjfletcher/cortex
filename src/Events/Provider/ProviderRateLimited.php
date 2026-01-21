<?php

declare(strict_types=1);

namespace JayI\Cortex\Events\Provider;

use JayI\Cortex\Events\CortexEvent;

class ProviderRateLimited extends CortexEvent
{
    public function __construct(
        public readonly string $provider,
        public readonly ?int $retryAfter = null,
        ?string $tenantId = null,
        ?string $correlationId = null,
        array $metadata = [],
    ) {
        parent::__construct($tenantId, $correlationId, $metadata);
    }
}
