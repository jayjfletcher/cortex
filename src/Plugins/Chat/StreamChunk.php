<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Chat;

use JayI\Cortex\Plugins\Chat\Messages\ToolUseContent;
use Spatie\LaravelData\Data;

class StreamChunk extends Data
{
    public function __construct(
        public StreamChunkType $type,
        public ?string $text = null,
        public ?ToolUseContent $toolUse = null,
        public ?Usage $usage = null,
        public ?StopReason $stopReason = null,
        public int $index = 0,
    ) {}

    /**
     * Create a text delta chunk.
     */
    public static function textDelta(string $text, int $index = 0): static
    {
        return new static(
            type: StreamChunkType::TextDelta,
            text: $text,
            index: $index,
        );
    }

    /**
     * Create a tool use start chunk.
     */
    public static function toolUseStart(ToolUseContent $toolUse, int $index = 0): static
    {
        return new static(
            type: StreamChunkType::ToolUseStart,
            toolUse: $toolUse,
            index: $index,
        );
    }

    /**
     * Create a message complete chunk.
     */
    public static function messageComplete(
        ?Usage $usage = null,
        ?StopReason $stopReason = null,
        int $index = 0
    ): static {
        return new static(
            type: StreamChunkType::MessageComplete,
            usage: $usage,
            stopReason: $stopReason,
            index: $index,
        );
    }

    /**
     * Check if this is a text chunk.
     */
    public function isText(): bool
    {
        return $this->type === StreamChunkType::TextDelta;
    }

    /**
     * Check if this is a tool use chunk.
     */
    public function isToolUse(): bool
    {
        return $this->type === StreamChunkType::ToolUseStart || $this->type === StreamChunkType::ToolUseDelta;
    }

    /**
     * Check if this is the final chunk.
     */
    public function isFinal(): bool
    {
        return $this->type === StreamChunkType::MessageComplete;
    }
}
