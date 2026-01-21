<?php

declare(strict_types=1);

namespace JayI\Cortex\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class CortexEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly float $timestamp;

    public readonly ?string $tenantId;

    public readonly ?string $correlationId;

    public readonly array $metadata;

    public function __construct(
        ?string $tenantId = null,
        ?string $correlationId = null,
        array $metadata = [],
    ) {
        $this->timestamp = microtime(true);
        $this->tenantId = $tenantId;
        $this->correlationId = $correlationId;
        $this->metadata = $metadata;
    }
}
