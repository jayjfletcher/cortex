<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Chat\Messages;

class TextContent extends Content
{
    public function __construct(
        public readonly string $text,
    ) {}

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'text',
            'text' => $this->text,
        ];
    }

    /**
     * Get the content type.
     */
    public function type(): string
    {
        return 'text';
    }
}
