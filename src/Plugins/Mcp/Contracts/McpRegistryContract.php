<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Mcp\Contracts;

use Illuminate\Support\Collection;

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
     *
     * @return Collection<string, McpServerContract>
     */
    public function all(): Collection;

    /**
     * Connect all servers.
     */
    public function connectAll(): void;

    /**
     * Disconnect all servers.
     */
    public function disconnectAll(): void;
}
