<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Chat\Messages;

use JayI\Cortex\Plugins\Chat\MessageRole;
use Spatie\LaravelData\Data;

class Message extends Data
{
    /**
     * @param  array<int, Content>  $content
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public MessageRole $role,
        public array $content,
        public ?string $name = null,
        public array $metadata = [],
    ) {}

    /**
     * Create a system message.
     */
    public static function system(string $content): static
    {
        return new static(
            role: MessageRole::System,
            content: [new TextContent($content)],
        );
    }

    /**
     * Create a user message.
     *
     * @param  string|Content|array<int, Content>  $content
     */
    public static function user(string|Content|array $content): static
    {
        return new static(
            role: MessageRole::User,
            content: self::normalizeContent($content),
        );
    }

    /**
     * Create an assistant message.
     *
     * @param  string|Content|array<int, Content>  $content
     */
    public static function assistant(string|Content|array $content): static
    {
        return new static(
            role: MessageRole::Assistant,
            content: self::normalizeContent($content),
        );
    }

    /**
     * Create a tool result message.
     */
    public static function toolResult(string $toolUseId, mixed $result, bool $isError = false): static
    {
        return new static(
            role: MessageRole::User,
            content: [new ToolResultContent($toolUseId, $result, $isError)],
        );
    }

    /**
     * Get text content from the message.
     */
    public function text(): ?string
    {
        $texts = [];
        foreach ($this->content as $content) {
            if ($content instanceof TextContent) {
                $texts[] = $content->text;
            }
        }

        return count($texts) > 0 ? implode("\n", $texts) : null;
    }

    /**
     * Get image contents from the message.
     *
     * @return array<int, ImageContent>
     */
    public function images(): array
    {
        return array_filter(
            $this->content,
            fn ($c) => $c instanceof ImageContent
        );
    }

    /**
     * Get tool call contents from the message.
     *
     * @return array<int, ToolUseContent>
     */
    public function toolCalls(): array
    {
        return array_values(array_filter(
            $this->content,
            fn ($c) => $c instanceof ToolUseContent
        ));
    }

    /**
     * Check if the message has tool calls.
     */
    public function hasToolCalls(): bool
    {
        return count($this->toolCalls()) > 0;
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'role' => $this->role->value,
            'content' => array_map(fn ($c) => $c->toArray(), $this->content),
            'name' => $this->name,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Normalize content to array of Content objects.
     *
     * @param  string|Content|array<int, Content>  $content
     * @return array<int, Content>
     */
    protected static function normalizeContent(string|Content|array $content): array
    {
        if (is_string($content)) {
            return [new TextContent($content)];
        }

        if ($content instanceof Content) {
            return [$content];
        }

        return $content;
    }
}
