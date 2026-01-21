<?php

declare(strict_types=1);

namespace JayI\Cortex\Exceptions;

use JayI\Cortex\Plugins\Schema\ValidationError;

class StructuredOutputException extends CortexException
{
    /**
     * Validation failed.
     *
     * @param  array<int, ValidationError>  $errors
     */
    public static function validationFailed(array $errors): static
    {
        $messages = array_map(fn (ValidationError $e) => $e->message, $errors);

        return static::make('Structured output validation failed: '.implode(', ', $messages))
            ->withContext([
                'errors' => array_map(fn (ValidationError $e) => [
                    'path' => $e->path,
                    'message' => $e->message,
                ], $errors),
            ]);
    }

    /**
     * Invalid data type.
     */
    public static function invalidDataType(string $expected, string $actual): static
    {
        return static::make("Expected data of type '{$expected}', got '{$actual}'")
            ->withContext(['expected' => $expected, 'actual' => $actual]);
    }

    /**
     * Failed to parse response.
     */
    public static function parseFailed(string $reason, ?string $rawContent = null): static
    {
        return static::make("Failed to parse structured output: {$reason}")
            ->withContext(['reason' => $reason, 'raw_content' => $rawContent]);
    }

    /**
     * Provider does not support structured output.
     */
    public static function unsupportedByProvider(string $provider): static
    {
        return static::make("Provider '{$provider}' does not support structured output")
            ->withContext(['provider' => $provider]);
    }

    /**
     * Max retries exceeded.
     */
    public static function maxRetriesExceeded(int $attempts): static
    {
        return static::make("Structured output validation failed after {$attempts} attempts")
            ->withContext(['attempts' => $attempts]);
    }
}
