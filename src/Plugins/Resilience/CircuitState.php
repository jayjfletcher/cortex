<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Resilience;

enum CircuitState: string
{
    case Closed = 'closed';
    case Open = 'open';
    case HalfOpen = 'half_open';

    /**
     * Check if the circuit allows requests.
     */
    public function allowsRequests(): bool
    {
        return $this !== self::Open;
    }
}
