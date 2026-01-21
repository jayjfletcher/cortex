<?php

declare(strict_types=1);

namespace JayI\Cortex\Exceptions;

class AgentException extends CortexException
{
    /**
     * Agent not found.
     */
    public static function notFound(string $id): static
    {
        return static::make("Agent '{$id}' not found")
            ->withContext(['agent_id' => $id]);
    }

    /**
     * Agent run failed.
     */
    public static function runFailed(string $id, string $message, ?\Throwable $previous = null): static
    {
        return static::make("Agent '{$id}' run failed: {$message}", previous: $previous)
            ->withContext(['agent_id' => $id]);
    }

    /**
     * Max iterations exceeded.
     */
    public static function maxIterationsExceeded(string $id, int $maxIterations): static
    {
        return static::make("Agent '{$id}' exceeded max iterations ({$maxIterations})")
            ->withContext([
                'agent_id' => $id,
                'max_iterations' => $maxIterations,
            ]);
    }

    /**
     * Invalid loop strategy.
     */
    public static function invalidLoopStrategy(string $strategy): static
    {
        return static::make("Invalid agent loop strategy: {$strategy}")
            ->withContext(['strategy' => $strategy]);
    }
}
