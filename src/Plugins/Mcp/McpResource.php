<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Mcp;

use Spatie\LaravelData\Data;

class McpResource extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $uri,
        public string $name,
        public ?string $description = null,
        public ?string $mimeType = null,
        public array $metadata = [],
    ) {}
}
