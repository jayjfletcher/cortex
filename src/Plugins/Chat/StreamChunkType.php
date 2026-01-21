<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Chat;

enum StreamChunkType: string
{
    case TextDelta = 'text_delta';
    case ToolUseStart = 'tool_use_start';
    case ToolUseDelta = 'tool_use_delta';
    case MessageComplete = 'message_complete';
}
