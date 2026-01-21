<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Mcp;

use Spatie\LaravelData\Data;

class McpPromptArgument extends Data
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public bool $required = false,
    ) {}
}
