<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Chat;

use JayI\Cortex\Plugins\Chat\Messages\Message;
use JayI\Cortex\Plugins\Chat\Messages\TextContent;
use JayI\Cortex\Plugins\Chat\Messages\ToolUseContent;
use Spatie\LaravelData\Data;

class ChatResponse extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public Message $message,
        public Usage $usage,
        public StopReason $stopReason,
        public ?string $model = null,
        public array $metadata = [],
    ) {}

    /**
     * Create a response from a simple text content.
     */
    public static function fromText(
        string $content,
        ?Usage $usage = null,
        StopReason $stopReason = StopReason::EndTurn,
        ?string $model = null
    ): static {
        return new static(
            message: Message::assistant($content),
            usage: $usage ?? Usage::zero(),
            stopReason: $stopReason,
            model: $model,
        );
    }

    /**
     * Get the text content of the response.
     */
    public function content(): string
    {
        return $this->message->text() ?? '';
    }

    /**
     * Get tool calls from the response.
     *
     * @return array<int, ToolUseContent>
     */
    public function toolCalls(): array
    {
        return $this->message->toolCalls();
    }

    /**
     * Check if the response has tool calls.
     */
    public function hasToolCalls(): bool
    {
        return $this->message->hasToolCalls();
    }

    /**
     * Get the first tool call.
     */
    public function firstToolCall(): ?ToolUseContent
    {
        $toolCalls = $this->toolCalls();

        return $toolCalls[0] ?? null;
    }

    /**
     * Check if the response completed naturally.
     */
    public function isComplete(): bool
    {
        return $this->stopReason->isComplete();
    }

    /**
     * Check if the response was truncated.
     */
    public function isTruncated(): bool
    {
        return $this->stopReason->isTruncated();
    }

    /**
     * Check if the response requires tool execution.
     */
    public function requiresToolExecution(): bool
    {
        return $this->stopReason->requiresToolExecution();
    }
}
