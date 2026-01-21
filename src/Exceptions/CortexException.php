<?php

declare(strict_types=1);

namespace JayI\Cortex\Exceptions;

use Exception;
use Throwable;

class CortexException extends Exception
{
    /**
     * @var array<string, mixed>
     */
    protected array $context = [];

    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create a new exception instance.
     */
    public static function make(string $message, int $code = 0, ?Throwable $previous = null): static
    {
        return new static($message, $code, $previous);
    }

    /**
     * Add context to the exception.
     *
     * @param  array<string, mixed>  $context
     */
    public function withContext(array $context): static
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    /**
     * Get the exception context.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
