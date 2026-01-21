<?php

declare(strict_types=1);

namespace JayI\Cortex\Exceptions;

class WorkflowException extends CortexException
{
    /**
     * Workflow not found.
     */
    public static function workflowNotFound(string $id): static
    {
        return static::make("Workflow '{$id}' not found")
            ->withContext(['workflow_id' => $id]);
    }

    /**
     * Node not found in workflow.
     */
    public static function nodeNotFound(string $nodeId): static
    {
        return static::make("Node '{$nodeId}' not found in workflow")
            ->withContext(['node_id' => $nodeId]);
    }

    /**
     * Invalid workflow state.
     */
    public static function invalidState(string $state): static
    {
        return static::make("Cannot resume workflow in '{$state}' state")
            ->withContext(['state' => $state]);
    }

    /**
     * Workflow execution failed.
     */
    public static function executionFailed(string $workflowId, string $message, ?\Throwable $previous = null): static
    {
        return static::make("Workflow '{$workflowId}' execution failed: {$message}", previous: $previous)
            ->withContext(['workflow_id' => $workflowId]);
    }

    /**
     * Node execution failed.
     */
    public static function nodeExecutionFailed(string $nodeId, string $message, ?\Throwable $previous = null): static
    {
        return static::make("Node '{$nodeId}' execution failed: {$message}", previous: $previous)
            ->withContext(['node_id' => $nodeId]);
    }

    /**
     * Invalid edge configuration.
     */
    public static function invalidEdge(string $from, string $to, string $reason): static
    {
        return static::make("Invalid edge from '{$from}' to '{$to}': {$reason}")
            ->withContext([
                'from_node' => $from,
                'to_node' => $to,
            ]);
    }

    /**
     * Maximum steps exceeded.
     */
    public static function maxStepsExceeded(string $workflowId, int $maxSteps): static
    {
        return static::make("Workflow '{$workflowId}' exceeded max steps ({$maxSteps})")
            ->withContext([
                'workflow_id' => $workflowId,
                'max_steps' => $maxSteps,
            ]);
    }

    /**
     * Circular dependency detected.
     */
    public static function circularDependency(string $workflowId, array $path): static
    {
        $pathStr = implode(' -> ', $path);

        return static::make("Circular dependency detected in workflow '{$workflowId}': {$pathStr}")
            ->withContext([
                'workflow_id' => $workflowId,
                'path' => $path,
            ]);
    }
}
