<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Mcp;

use Spatie\LaravelData\Data;

class McpResourceContent extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $uri,
        public string $content,
        public ?string $mimeType = null,
        public array $metadata = [],
    ) {}

    /**
     * Check if content is text.
     */
    public function isText(): bool
    {
        return $this->mimeType === null
            || str_starts_with($this->mimeType, 'text/')
            || $this->mimeType === 'application/json';
    }

    /**
     * Check if content is binary.
     */
    public function isBinary(): bool
    {
        return ! $this->isText();
    }

    /**
     * Decode content as JSON.
     *
     * @return array<string, mixed>|null
     */
    public function json(): ?array
    {
        $decoded = json_decode($this->content, true);

        return is_array($decoded) ? $decoded : null;
    }
}
