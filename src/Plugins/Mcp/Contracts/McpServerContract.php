<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Mcp\Contracts;

use JayI\Cortex\Plugins\Mcp\McpPromptResult;
use JayI\Cortex\Plugins\Mcp\McpResourceContent;
use JayI\Cortex\Plugins\Mcp\McpTransport;
use JayI\Cortex\Plugins\Tool\ToolCollection;

interface McpServerContract
{
    /**
     * Get the server's unique identifier.
     */
    public function id(): string;

    /**
     * Get the server's display name.
     */
    public function name(): string;

    /**
     * Get the transport type.
     */
    public function transport(): McpTransport;

    /**
     * Connect to the server.
     */
    public function connect(): void;

    /**
     * Disconnect from the server.
     */
    public function disconnect(): void;

    /**
     * Check if connected.
     */
    public function isConnected(): bool;

    /**
     * Get available tools from the server.
     */
    public function tools(): ToolCollection;

    /**
     * List available resources.
     *
     * @return array<int, McpResource>
     */
    public function resources(): array;

    /**
     * Read a resource.
     */
    public function readResource(string $uri): McpResourceContent;

    /**
     * List available prompts.
     *
     * @return array<int, McpPrompt>
     */
    public function prompts(): array;

    /**
     * Get a prompt.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function getPrompt(string $name, array $arguments = []): McpPromptResult;
}
