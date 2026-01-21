<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Mcp;

use JayI\Cortex\Plugins\Chat\MessageRole;
use JayI\Cortex\Plugins\Chat\Messages\Message;
use Spatie\LaravelData\Data;

class McpPromptMessage extends Data
{
    public function __construct(
        public string $role,
        public ?string $content = null,
    ) {}

    /**
     * Convert to a Chat Message.
     */
    public function toMessage(): Message
    {
        $role = match ($this->role) {
            'user' => MessageRole::User,
            'assistant' => MessageRole::Assistant,
            'system' => MessageRole::System,
            default => MessageRole::User,
        };

        return match ($role) {
            MessageRole::System => Message::system($this->content ?? ''),
            MessageRole::User => Message::user($this->content ?? ''),
            MessageRole::Assistant => Message::assistant($this->content ?? ''),
        };
    }
}
