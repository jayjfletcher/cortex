<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Tool\Contracts;

use Illuminate\Support\Collection;
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
     *
     * @return Collection<string, ToolContract>
     */
    public function all(): Collection;

    /**
     * Create a collection with specific tools.
     */
    public function collection(string ...$names): ToolCollection;

    /**
     * Auto-discover tools from configured paths.
     */
    public function discover(): void;
}
