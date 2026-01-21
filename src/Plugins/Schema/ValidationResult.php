<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Schema;

use JayI\Cortex\Exceptions\SchemaValidationException;
use Spatie\LaravelData\Data;

class ValidationResult extends Data
{
    /**
     * @param  array<int, ValidationError>  $errors
     */
    public function __construct(
        public bool $valid,
        public array $errors = [],
    ) {}

    /**
     * Create a valid result.
     */
    public static function valid(): static
    {
        return new static(valid: true);
    }

    /**
     * Create an invalid result with errors.
     *
     * @param  array<int, ValidationError>  $errors
     */
    public static function invalid(array $errors): static
    {
        return new static(valid: false, errors: $errors);
    }

    /**
     * Create an invalid result with a single error.
     */
    public static function error(string $path, string $message, mixed $value = null): static
    {
        return static::invalid([
            new ValidationError($path, $message, $value),
        ]);
    }

    /**
     * Check if the result is valid.
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * Check if the result has errors.
     */
    public function hasErrors(): bool
    {
        return ! $this->valid;
    }

    /**
     * Merge this result with another result.
     */
    public function merge(ValidationResult $other): static
    {
        if ($this->valid && $other->valid) {
            return static::valid();
        }

        return static::invalid(array_merge($this->errors, $other->errors));
    }

    /**
     * Throw an exception if the result is invalid.
     *
     * @throws SchemaValidationException
     */
    public function throw(): void
    {
        if (! $this->valid) {
            throw SchemaValidationException::withErrors($this->errors);
        }
    }

    /**
     * Get error messages as an array.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];
        foreach ($this->errors as $error) {
            $messages[$error->path] = $error->message;
        }

        return $messages;
    }
}
