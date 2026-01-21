<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Resilience\Strategies;

use Closure;
use JayI\Cortex\Plugins\Resilience\Contracts\ResilienceStrategyContract;
use Throwable;

/**
 * Provide fallback values when operations fail.
 */
class FallbackStrategy implements ResilienceStrategyContract
{
    /**
     * @param  Closure(Throwable): mixed  $fallback  Fallback function receiving the exception
     * @param  array<int, class-string<Throwable>>  $handleOn  Exception classes to handle
     */
    public function __construct(
        protected Closure $fallback,
        protected array $handleOn = [],
    ) {}

    /**
     * {@inheritdoc}
     */
    public function execute(Closure $operation): mixed
    {
        try {
            return $operation();
        } catch (Throwable $e) {
            if ($this->shouldHandle($e)) {
                return ($this->fallback)($e);
            }

            throw $e;
        }
    }

    /**
     * Check if the exception should be handled by fallback.
     */
    protected function shouldHandle(Throwable $e): bool
    {
        if (empty($this->handleOn)) {
            return true;
        }

        foreach ($this->handleOn as $exceptionClass) {
            if ($e instanceof $exceptionClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a fallback that returns a static value.
     */
    public static function value(mixed $value): self
    {
        return new self(fn () => $value);
    }

    /**
     * Create a fallback that returns null.
     */
    public static function null(): self
    {
        return new self(fn () => null);
    }

    /**
     * Create a fallback that returns an empty array.
     */
    public static function emptyArray(): self
    {
        return new self(fn () => []);
    }
}
