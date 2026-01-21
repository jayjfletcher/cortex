<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Resilience\Strategies;

use Closure;
use JayI\Cortex\Plugins\Resilience\CircuitState;
use JayI\Cortex\Plugins\Resilience\Contracts\ResilienceStrategyContract;
use JayI\Cortex\Plugins\Resilience\Exceptions\CircuitOpenException;
use Throwable;

/**
 * Circuit breaker pattern to prevent cascading failures.
 */
class CircuitBreakerStrategy implements ResilienceStrategyContract
{
    protected CircuitState $state = CircuitState::Closed;

    protected int $failureCount = 0;

    protected int $successCount = 0;

    protected ?int $openedAt = null;

    /**
     * @param  array<int, class-string<Throwable>>  $tripOn  Exception classes that trip the breaker
     */
    public function __construct(
        protected int $failureThreshold = 5,
        protected int $successThreshold = 3,
        protected int $resetTimeoutSeconds = 60,
        protected array $tripOn = [],
    ) {}

    /**
     * {@inheritdoc}
     */
    public function execute(Closure $operation): mixed
    {
        if (! $this->allowsRequest()) {
            throw new CircuitOpenException(
                'Circuit breaker is open',
                $this->getRemainingCooldown()
            );
        }

        try {
            $result = $operation();
            $this->recordSuccess();

            return $result;
        } catch (Throwable $e) {
            if ($this->shouldTrip($e)) {
                $this->recordFailure();
            }
            throw $e;
        }
    }

    /**
     * Check if the circuit allows requests.
     */
    public function allowsRequest(): bool
    {
        if ($this->state === CircuitState::Open) {
            if ($this->shouldAttemptReset()) {
                $this->transitionTo(CircuitState::HalfOpen);

                return true;
            }

            return false;
        }

        return true;
    }

    /**
     * Record a successful operation.
     */
    protected function recordSuccess(): void
    {
        if ($this->state === CircuitState::HalfOpen) {
            $this->successCount++;

            if ($this->successCount >= $this->successThreshold) {
                $this->reset();
            }
        } elseif ($this->state === CircuitState::Closed) {
            // Reset failure count on success in closed state
            $this->failureCount = 0;
        }
    }

    /**
     * Record a failed operation.
     */
    protected function recordFailure(): void
    {
        if ($this->state === CircuitState::HalfOpen) {
            $this->trip();
        } elseif ($this->state === CircuitState::Closed) {
            $this->failureCount++;

            if ($this->failureCount >= $this->failureThreshold) {
                $this->trip();
            }
        }
    }

    /**
     * Check if the exception should trip the breaker.
     */
    protected function shouldTrip(Throwable $e): bool
    {
        if (empty($this->tripOn)) {
            return true;
        }

        foreach ($this->tripOn as $exceptionClass) {
            if ($e instanceof $exceptionClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if we should attempt to reset the circuit.
     */
    protected function shouldAttemptReset(): bool
    {
        if ($this->openedAt === null) {
            return false;
        }

        return (time() - $this->openedAt) >= $this->resetTimeoutSeconds;
    }

    /**
     * Trip the circuit breaker.
     */
    protected function trip(): void
    {
        $this->transitionTo(CircuitState::Open);
        $this->openedAt = time();
        $this->failureCount = 0;
        $this->successCount = 0;
    }

    /**
     * Reset the circuit breaker.
     */
    public function reset(): void
    {
        $this->transitionTo(CircuitState::Closed);
        $this->openedAt = null;
        $this->failureCount = 0;
        $this->successCount = 0;
    }

    /**
     * Transition to a new state.
     */
    protected function transitionTo(CircuitState $state): void
    {
        $this->state = $state;
    }

    /**
     * Get the current state.
     */
    public function getState(): CircuitState
    {
        return $this->state;
    }

    /**
     * Get the failure count.
     */
    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    /**
     * Get the success count in half-open state.
     */
    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    /**
     * Get remaining cooldown time in seconds.
     */
    public function getRemainingCooldown(): int
    {
        if ($this->openedAt === null || $this->state !== CircuitState::Open) {
            return 0;
        }

        $elapsed = time() - $this->openedAt;
        $remaining = $this->resetTimeoutSeconds - $elapsed;

        return max(0, $remaining);
    }

    /**
     * Force the circuit to a specific state (for testing).
     */
    public function forceState(CircuitState $state): void
    {
        $this->state = $state;

        if ($state === CircuitState::Open) {
            $this->openedAt = time();
        }
    }
}
