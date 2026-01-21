<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Resilience\Strategies;

use Closure;
use JayI\Cortex\Plugins\Resilience\Contracts\ResilienceStrategyContract;
use JayI\Cortex\Plugins\Resilience\Exceptions\BulkheadRejectedException;

/**
 * Bulkhead pattern to isolate resources and limit concurrent executions.
 */
class BulkheadStrategy implements ResilienceStrategyContract
{
    protected int $activeCount = 0;

    protected int $queuedCount = 0;

    /** @var \SplQueue<array{operation: Closure, resolve: Closure, reject: Closure}> */
    protected \SplQueue $queue;

    public function __construct(
        protected int $maxConcurrent = 10,
        protected int $maxQueue = 100,
    ) {
        $this->queue = new \SplQueue;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(Closure $operation): mixed
    {
        if ($this->activeCount < $this->maxConcurrent) {
            return $this->executeOperation($operation);
        }

        if ($this->queuedCount >= $this->maxQueue) {
            throw new BulkheadRejectedException(
                "Bulkhead rejected: {$this->activeCount} active, {$this->queuedCount} queued",
                $this->activeCount,
                $this->queuedCount
            );
        }

        // Queue the operation and wait
        return $this->queueAndWait($operation);
    }

    /**
     * Execute an operation with tracking.
     */
    protected function executeOperation(Closure $operation): mixed
    {
        $this->activeCount++;

        try {
            $result = $operation();

            return $result;
        } finally {
            $this->activeCount--;
            $this->processQueue();
        }
    }

    /**
     * Queue an operation and wait for execution.
     */
    protected function queueAndWait(Closure $operation): mixed
    {
        $this->queuedCount++;

        // In a synchronous context, we can only wait by polling
        // For true async, this would use promises/fibers
        $maxWaitMs = 30000;
        $waited = 0;
        $sleepMs = 10;

        while ($waited < $maxWaitMs) {
            if ($this->activeCount < $this->maxConcurrent) {
                $this->queuedCount--;

                return $this->executeOperation($operation);
            }

            usleep($sleepMs * 1000);
            $waited += $sleepMs;
        }

        $this->queuedCount--;
        throw new BulkheadRejectedException(
            'Bulkhead wait timeout exceeded',
            $this->activeCount,
            $this->queuedCount
        );
    }

    /**
     * Process queued operations.
     */
    protected function processQueue(): void
    {
        // In synchronous PHP, the queue processing happens
        // automatically as operations complete
    }

    /**
     * Get the current active count.
     */
    public function getActiveCount(): int
    {
        return $this->activeCount;
    }

    /**
     * Get the current queued count.
     */
    public function getQueuedCount(): int
    {
        return $this->queuedCount;
    }

    /**
     * Get available slots.
     */
    public function getAvailableSlots(): int
    {
        return max(0, $this->maxConcurrent - $this->activeCount);
    }

    /**
     * Check if the bulkhead is at capacity.
     */
    public function isAtCapacity(): bool
    {
        return $this->activeCount >= $this->maxConcurrent;
    }

    /**
     * Reset the bulkhead state.
     */
    public function reset(): void
    {
        $this->activeCount = 0;
        $this->queuedCount = 0;
        $this->queue = new \SplQueue;
    }
}
