<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Chat;

enum StopReason: string
{
    case EndTurn = 'end_turn';
    case MaxTokens = 'max_tokens';
    case StopSequence = 'stop_sequence';
    case ToolUse = 'tool_use';
    case ContentFiltered = 'content_filtered';

    /**
     * Check if the response completed naturally.
     */
    public function isComplete(): bool
    {
        return $this === self::EndTurn;
    }

    /**
     * Check if the response was truncated.
     */
    public function isTruncated(): bool
    {
        return $this === self::MaxTokens;
    }

    /**
     * Check if the response requires tool execution.
     */
    public function requiresToolExecution(): bool
    {
        return $this === self::ToolUse;
    }
}
