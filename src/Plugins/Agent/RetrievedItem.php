<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent;

use Spatie\LaravelData\Data;

class RetrievedItem extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $content,
        public float $score = 1.0,
        public array $metadata = [],
    ) {}

    /**
     * Create an item from an array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        return new static(
            content: $data['content'] ?? '',
            score: $data['score'] ?? 1.0,
            metadata: $data['metadata'] ?? $data,
        );
    }

    /**
     * Get a metadata value.
     */
    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Format the item for inclusion in a prompt.
     */
    public function toContext(): string
    {
        $context = $this->content;

        // Add source reference if available
        $source = $this->getMeta('source') ?? $this->getMeta('url') ?? $this->getMeta('title');
        if ($source !== null) {
            $context .= "\n[Source: {$source}]";
        }

        return $context;
    }
}
