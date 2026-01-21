<?php

declare(strict_types=1);

namespace JayI\Cortex\Exceptions;

class McpException extends CortexException
{
    /**
     * Server not found.
     */
    public static function serverNotFound(string $id): static
    {
        return static::make("MCP server '{$id}' not found")
            ->withContext(['server_id' => $id]);
    }

    /**
     * Connection failed.
     */
    public static function connectionFailed(string $id, string $message, ?\Throwable $previous = null): static
    {
        return static::make("Failed to connect to MCP server '{$id}': {$message}", previous: $previous)
            ->withContext(['server_id' => $id]);
    }

    /**
     * Server not connected.
     */
    public static function notConnected(string $id): static
    {
        return static::make("MCP server '{$id}' is not connected")
            ->withContext(['server_id' => $id]);
    }

    /**
     * Tool not found.
     */
    public static function toolNotFound(string $serverId, string $toolName): static
    {
        return static::make("Tool '{$toolName}' not found on MCP server '{$serverId}'")
            ->withContext([
                'server_id' => $serverId,
                'tool_name' => $toolName,
            ]);
    }

    /**
     * Resource not found.
     */
    public static function resourceNotFound(string $serverId, string $uri): static
    {
        return static::make("Resource '{$uri}' not found on MCP server '{$serverId}'")
            ->withContext([
                'server_id' => $serverId,
                'resource_uri' => $uri,
            ]);
    }

    /**
     * Prompt not found.
     */
    public static function promptNotFound(string $serverId, string $promptName): static
    {
        return static::make("Prompt '{$promptName}' not found on MCP server '{$serverId}'")
            ->withContext([
                'server_id' => $serverId,
                'prompt_name' => $promptName,
            ]);
    }

    /**
     * Protocol error.
     */
    public static function protocolError(string $serverId, string $message): static
    {
        return static::make("MCP protocol error on server '{$serverId}': {$message}")
            ->withContext(['server_id' => $serverId]);
    }
}
