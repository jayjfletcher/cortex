<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Tool\Contracts;

use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Tool\ToolContext;
use JayI\Cortex\Plugins\Tool\ToolResult;

interface ToolContract
{
    /**
     * Unique tool name (used in LLM calls).
     */
    public function name(): string;

    /**
     * Human-readable description for the LLM.
     */
    public function description(): string;

    /**
     * Input parameter schema.
     */
    public function inputSchema(): Schema;

    /**
     * Optional output schema for structured results.
     */
    public function outputSchema(): ?Schema;

    /**
     * Execute the tool with validated input.
     *
     * @param  array<string, mixed>  $input
     */
    public function execute(array $input, ToolContext $context): ToolResult;

    /**
     * Timeout in seconds (null for no timeout).
     */
    public function timeout(): ?int;

    /**
     * Convert to tool definition array for LLM API calls.
     *
     * @return array<string, mixed>
     */
    public function toDefinition(): array;
}
