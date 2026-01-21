<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Mcp\Contracts;

use JayI\Cortex\Plugins\Mcp\McpServerCollection;

interface McpRegistryContract
{
    /**
     * Register an MCP server.
     */
    public function register(McpServerContract $server): void;

    /**
     * Get an MCP server by ID.
     */
    public function get(string $id): McpServerContract;

    /**
     * Check if a server exists.
     */
    public function has(string $id): bool;

    /**
     * Get all registered servers.
     */
    public function all(): McpServerCollection;

    /**
     * Get only the specified servers.
     *
     * @param  array<int, string>  $ids
     */
    public function only(array $ids): McpServerCollection;

    /**
     * Get all servers except the specified ones.
     *
     * @param  array<int, string>  $ids
     */
    public function except(array $ids): McpServerCollection;

    /**
     * Connect all servers.
     */
    public function connectAll(): void;

    /**
     * Disconnect all servers.
     */
    public function disconnectAll(): void;
}
