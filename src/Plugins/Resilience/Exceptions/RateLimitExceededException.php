<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Resilience\Exceptions;

use Exception;

/**
 * Exception thrown when rate limit is exceeded.
 */
class RateLimitExceededException extends Exception
{
    public function __construct(
        string $message = 'Rate limit exceeded',
        public readonly float $retryAfterSeconds = 0.0,
    ) {
        parent::__construct($message);
    }
}
