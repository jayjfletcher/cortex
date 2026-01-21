<?php

declare(strict_types=1);

namespace JayI\Cortex\Exceptions;

class ToolException extends CortexException
{
    /**
     * Tool not found in registry.
     */
    public static function notFound(string $name): static
    {
        return static::make("Tool '{$name}' not found in registry")
            ->withContext(['tool' => $name]);
    }

    /**
     * Tool already registered.
     */
    public static function alreadyRegistered(string $name): static
    {
        return static::make("Tool '{$name}' is already registered")
            ->withContext(['tool' => $name]);
    }

    /**
     * Tool execution failed.
     */
    public static function executionFailed(string $name, string $reason, ?\Throwable $previous = null): static
    {
        $exception = new static("Tool '{$name}' execution failed: {$reason}", 0, $previous);

        return $exception->withContext(['tool' => $name, 'reason' => $reason]);
    }

    /**
     * Tool execution timed out.
     */
    public static function timeout(string $name, int $seconds): static
    {
        return static::make("Tool '{$name}' execution timed out after {$seconds} seconds")
            ->withContext(['tool' => $name, 'timeout' => $seconds]);
    }

    /**
     * Invalid tool class.
     */
    public static function invalidClass(string $class, string $reason): static
    {
        return static::make("Invalid tool class '{$class}': {$reason}")
            ->withContext(['class' => $class, 'reason' => $reason]);
    }

    /**
     * Tool input validation failed.
     *
     * @param  array<int|string, mixed>  $errors
     */
    public static function validationFailed(string $name, array $errors): static
    {
        return static::make("Tool '{$name}' input validation failed")
            ->withContext(['tool' => $name, 'errors' => $errors]);
    }
}
