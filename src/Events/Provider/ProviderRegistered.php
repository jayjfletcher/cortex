<?php

declare(strict_types=1);

namespace JayI\Cortex\Events\Provider;

use JayI\Cortex\Events\CortexEvent;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderContract;

class ProviderRegistered extends CortexEvent
{
    public function __construct(
        public readonly ProviderContract|string $provider,
        public readonly string $providerId,
        public readonly array $capabilities = [],
        ?string $tenantId = null,
        ?string $correlationId = null,
        array $metadata = [],
    ) {
        parent::__construct($tenantId, $correlationId, $metadata);
    }
}
