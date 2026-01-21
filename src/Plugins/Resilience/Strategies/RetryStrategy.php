<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Resilience\Strategies;

use Closure;
use JayI\Cortex\Plugins\Resilience\Contracts\ResilienceStrategyContract;
use Throwable;

/**
 * Retry failed operations with exponential backoff.
 */
class RetryStrategy implements ResilienceStrategyContract
{
    /**
     * @param  array<int, class-string<Throwable>>  $retryOn  Exception classes to retry on
     */
    public function __construct(
        protected int $maxAttempts = 3,
        protected int $delayMs = 1000,
        protected float $multiplier = 2.0,
        protected int $maxDelayMs = 30000,
        protected bool $jitter = true,
        protected array $retryOn = [],
    ) {}

    /**
     * {@inheritdoc}
     */
    public function execute(Closure $operation): mixed
    {
        $attempt = 0;
        $delay = $this->delayMs;

        while (true) {
            $attempt++;

            try {
                return $operation();
            } catch (Throwable $e) {
                if ($attempt >= $this->maxAttempts || ! $this->shouldRetry($e)) {
                    throw $e;
                }

                $this->sleep($delay);

                // Calculate next delay with exponential backoff
                $delay = (int) min($delay * $this->multiplier, $this->maxDelayMs);
            }
        }
    }

    /**
     * Check if the exception should trigger a retry.
     */
    protected function shouldRetry(Throwable $e): bool
    {
        if (empty($this->retryOn)) {
            return true;
        }

        foreach ($this->retryOn as $exceptionClass) {
            if ($e instanceof $exceptionClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sleep for the specified duration with optional jitter.
     */
    protected function sleep(int $delayMs): void
    {
        $actualDelay = $delayMs;

        if ($this->jitter) {
            // Add random jitter of up to 25% of the delay
            $jitterAmount = (int) ($delayMs * 0.25);
            $actualDelay = $delayMs + random_int(-$jitterAmount, $jitterAmount);
            $actualDelay = max(0, $actualDelay);
        }

        usleep($actualDelay * 1000);
    }

    /**
     * Get the maximum number of attempts.
     */
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * Get the initial delay in milliseconds.
     */
    public function getDelayMs(): int
    {
        return $this->delayMs;
    }
}
