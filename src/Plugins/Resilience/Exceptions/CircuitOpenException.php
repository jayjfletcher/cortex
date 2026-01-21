<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Resilience\Exceptions;

use Exception;

/**
 * Exception thrown when a circuit breaker is open.
 */
class CircuitOpenException extends Exception
{
    public function __construct(
        string $message = 'Circuit breaker is open',
        public readonly int $remainingCooldown = 0,
    ) {
        parent::__construct($message);
    }
}
