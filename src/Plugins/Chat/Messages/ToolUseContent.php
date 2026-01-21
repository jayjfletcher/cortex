<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Chat\Messages;

class ToolUseContent extends Content
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $input,
    ) {}

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => 'tool_use',
            'id' => $this->id,
            'name' => $this->name,
            'input' => $this->input,
        ];
    }

    /**
     * Get the content type.
     */
    public function type(): string
    {
        return 'tool_use';
    }
}
