<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Schema;

use Spatie\LaravelData\Data;

class ValidationError extends Data
{
    public function __construct(
        public string $path,
        public string $message,
        public mixed $value = null,
    ) {}

    /**
     * Create a validation error.
     */
    public static function make(string $path, string $message, mixed $value = null): static
    {
        return new static($path, $message, $value);
    }

    /**
     * Get a formatted error string.
     */
    public function toString(): string
    {
        return "[{$this->path}]: {$this->message}";
    }
}
