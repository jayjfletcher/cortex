<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\StructuredOutput;

use JayI\Cortex\Exceptions\StructuredOutputException;
use JayI\Cortex\Plugins\Chat\ChatResponse;
use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Schema\ValidationError;
use Spatie\LaravelData\Data;

class StructuredResponse extends Data
{
    /**
     * @param  array<int, ValidationError>  $validationErrors
     */
    public function __construct(
        public mixed $data,
        public Schema $schema,
        public bool $valid,
        public array $validationErrors,
        public ChatResponse $rawResponse,
    ) {}

    /**
     * Create a valid response.
     */
    public static function valid(mixed $data, Schema $schema, ChatResponse $rawResponse): static
    {
        return new static(
            data: $data,
            schema: $schema,
            valid: true,
            validationErrors: [],
            rawResponse: $rawResponse,
        );
    }

    /**
     * Create an invalid response.
     *
     * @param  array<int, ValidationError>  $errors
     */
    public static function invalid(mixed $data, Schema $schema, array $errors, ChatResponse $rawResponse): static
    {
        return new static(
            data: $data,
            schema: $schema,
            valid: false,
            validationErrors: $errors,
            rawResponse: $rawResponse,
        );
    }

    /**
     * Convert to a Data class instance.
     *
     * @template T of object
     *
     * @param  class-string<T>  $class
     * @return T
     */
    public function toData(string $class): object
    {
        if (! $this->valid) {
            throw StructuredOutputException::validationFailed($this->validationErrors);
        }

        if (! is_array($this->data)) {
            throw StructuredOutputException::invalidDataType('array', gettype($this->data));
        }

        if (is_subclass_of($class, Data::class)) {
            return $class::from($this->data);
        }

        return new $class(...$this->data);
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if (! is_array($this->data)) {
            return ['data' => $this->data];
        }

        return $this->data;
    }

    /**
     * Get validation error messages.
     *
     * @return array<int, string>
     */
    public function errorMessages(): array
    {
        return array_map(fn (ValidationError $e) => $e->message, $this->validationErrors);
    }

    /**
     * Throw exception if invalid.
     */
    public function throw(): static
    {
        if (! $this->valid) {
            throw StructuredOutputException::validationFailed($this->validationErrors);
        }

        return $this;
    }

    /**
     * Get a value from the data by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (! is_array($this->data)) {
            return $default;
        }

        return $this->data[$key] ?? $default;
    }
}
