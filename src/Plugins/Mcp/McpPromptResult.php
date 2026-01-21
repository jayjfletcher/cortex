<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Mcp;

use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;
use Spatie\LaravelData\Data;

class McpPromptResult extends Data
{
    /**
     * @param  array<int, McpPromptMessage>  $messages
     */
    public function __construct(
        public ?string $description = null,
        public array $messages = [],
    ) {}

    /**
     * Convert to a MessageCollection for use in chat.
     */
    public function toMessageCollection(): MessageCollection
    {
        $collection = MessageCollection::make();

        foreach ($this->messages as $message) {
            $collection->add($message->toMessage());
        }

        return $collection;
    }

    /**
     * Get combined text content.
     */
    public function text(): string
    {
        $texts = [];
        foreach ($this->messages as $message) {
            if ($message->content !== null) {
                $texts[] = $message->content;
            }
        }

        return implode("\n", $texts);
    }
}
