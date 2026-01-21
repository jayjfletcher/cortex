<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Resilience\Strategies;

use Closure;
use JayI\Cortex\Plugins\Resilience\Contracts\ResilienceStrategyContract;
use JayI\Cortex\Plugins\Resilience\Exceptions\RateLimitExceededException;

/**
 * Rate limiting using token bucket algorithm.
 */
class RateLimiterStrategy implements ResilienceStrategyContract
{
    protected float $tokens;

    protected float $lastRefill;

    public function __construct(
        protected int $maxTokens = 10,
        protected float $refillRate = 1.0, // Tokens per second
        protected bool $waitForToken = false,
        protected int $maxWaitSeconds = 60,
    ) {
        $this->tokens = (float) $maxTokens;
        $this->lastRefill = microtime(true);
    }

    /**
     * {@inheritdoc}
     */
    public function execute(Closure $operation): mixed
    {
        if (! $this->acquire()) {
            throw new RateLimitExceededException(
                'Rate limit exceeded',
                $this->getTimeUntilNextToken()
            );
        }

        return $operation();
    }

    /**
     * Attempt to acquire a token.
     */
    protected function acquire(): bool
    {
        $this->refill();

        if ($this->tokens >= 1) {
            $this->tokens -= 1;

            return true;
        }

        if ($this->waitForToken) {
            return $this->waitAndAcquire();
        }

        return false;
    }

    /**
     * Wait for a token to become available.
     */
    protected function waitAndAcquire(): bool
    {
        $waitTime = $this->getTimeUntilNextToken();

        if ($waitTime > $this->maxWaitSeconds) {
            return false;
        }

        // Sleep until token is available
        usleep((int) ($waitTime * 1_000_000));

        $this->refill();

        if ($this->tokens >= 1) {
            $this->tokens -= 1;

            return true;
        }

        return false;
    }

    /**
     * Refill tokens based on elapsed time.
     */
    protected function refill(): void
    {
        $now = microtime(true);
        $elapsed = $now - $this->lastRefill;
        $tokensToAdd = $elapsed * $this->refillRate;

        $this->tokens = min($this->maxTokens, $this->tokens + $tokensToAdd);
        $this->lastRefill = $now;
    }

    /**
     * Get seconds until the next token is available.
     */
    public function getTimeUntilNextToken(): float
    {
        if ($this->tokens >= 1) {
            return 0.0;
        }

        $needed = 1 - $this->tokens;

        return $needed / $this->refillRate;
    }

    /**
     * Get current available tokens.
     */
    public function getAvailableTokens(): float
    {
        $this->refill();

        return $this->tokens;
    }

    /**
     * Reset the rate limiter to full capacity.
     */
    public function reset(): void
    {
        $this->tokens = (float) $this->maxTokens;
        $this->lastRefill = microtime(true);
    }
}
