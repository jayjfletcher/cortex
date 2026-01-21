<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Tool\Contracts;

use JayI\Cortex\Plugins\Tool\ToolCollection;

interface ToolRegistryContract
{
    /**
     * Register a tool.
     *
     * @param  ToolContract|class-string<ToolContract>  $tool
     */
    public function register(ToolContract|string $tool): void;

    /**
     * Get a tool by name.
     */
    public function get(string $name): ToolContract;

    /**
     * Check if a tool exists.
     */
    public function has(string $name): bool;

    /**
     * Get all registered tools.
     */
    public function all(): ToolCollection;

    /**
     * Get only the specified tools.
     *
     * @param  array<int, string>  $names
     */
    public function only(array $names): ToolCollection;

    /**
     * Get all tools except the specified ones.
     *
     * @param  array<int, string>  $names
     */
    public function except(array $names): ToolCollection;

    /**
     * Create a collection with specific tools.
     */
    public function collection(string ...$names): ToolCollection;

    /**
     * Auto-discover tools from configured paths.
     */
    public function discover(): void;
}
