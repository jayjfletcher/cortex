<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Resilience\Exceptions;

use Exception;

/**
 * Exception thrown when bulkhead rejects a request.
 */
class BulkheadRejectedException extends Exception
{
    public function __construct(
        string $message = 'Bulkhead rejected request',
        public readonly int $activeCount = 0,
        public readonly int $queuedCount = 0,
    ) {
        parent::__construct($message);
    }
}
