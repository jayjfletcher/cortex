<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Mcp;

use Spatie\LaravelData\Data;

class McpPrompt extends Data
{
    /**
     * @param  array<int, McpPromptArgument>  $arguments
     */
    public function __construct(
        public string $name,
        public ?string $description = null,
        public array $arguments = [],
    ) {}
}
