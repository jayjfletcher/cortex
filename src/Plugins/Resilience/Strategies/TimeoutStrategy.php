<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Resilience\Strategies;

use Closure;
use JayI\Cortex\Plugins\Resilience\Contracts\ResilienceStrategyContract;
use JayI\Cortex\Plugins\Resilience\Exceptions\TimeoutException;

/**
 * Enforce timeout on operations.
 */
class TimeoutStrategy implements ResilienceStrategyContract
{
    public function __construct(
        protected int $timeoutSeconds = 30,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function execute(Closure $operation): mixed
    {
        $startTime = microtime(true);

        // Set a signal alarm for timeout
        if (function_exists('pcntl_alarm') && function_exists('pcntl_signal')) {
            $this->executeWithSignal($operation);
        }

        // For environments without pcntl, we do a simple time check
        // Note: This won't interrupt blocking operations
        $result = $operation();

        $elapsed = microtime(true) - $startTime;

        if ($elapsed > $this->timeoutSeconds) {
            throw new TimeoutException(
                "Operation timed out after {$this->timeoutSeconds} seconds",
                $this->timeoutSeconds
            );
        }

        return $result;
    }

    /**
     * Execute with pcntl signal-based timeout.
     *
     * @throws TimeoutException
     */
    protected function executeWithSignal(Closure $operation): mixed
    {
        $timedOut = false;

        // Store previous handler
        $previousHandler = pcntl_signal_get_handler(SIGALRM);

        // Set up alarm signal handler
        pcntl_signal(SIGALRM, function () use (&$timedOut): void {
            $timedOut = true;
        });

        // Set alarm
        pcntl_alarm($this->timeoutSeconds);

        try {
            $result = $operation();

            // Cancel alarm
            pcntl_alarm(0);

            // Restore previous handler
            if ($previousHandler !== false) {
                pcntl_signal(SIGALRM, $previousHandler);
            }

            if ($timedOut) {
                throw new TimeoutException(
                    "Operation timed out after {$this->timeoutSeconds} seconds",
                    $this->timeoutSeconds
                );
            }

            return $result;
        } catch (\Throwable $e) {
            // Cancel alarm on any exception
            pcntl_alarm(0);

            // Restore previous handler
            if ($previousHandler !== false) {
                pcntl_signal(SIGALRM, $previousHandler);
            }

            throw $e;
        }
    }

    /**
     * Get the timeout in seconds.
     */
    public function getTimeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }
}
