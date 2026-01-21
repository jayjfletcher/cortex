<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Resilience\Exceptions;

use Exception;

/**
 * Exception thrown when an operation times out.
 */
class TimeoutException extends Exception
{
    public function __construct(
        string $message = 'Operation timed out',
        public readonly int $timeoutSeconds = 0,
    ) {
        parent::__construct($message);
    }
}
