<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent;

enum AgentStopReason: string
{
    /**
     * Agent completed the task naturally.
     */
    case Completed = 'completed';

    /**
     * Agent hit max iterations limit.
     */
    case MaxIterations = 'max_iterations';

    /**
     * A tool signaled to stop execution.
     */
    case ToolStopped = 'tool_stopped';

    /**
     * Agent was manually stopped.
     */
    case Cancelled = 'cancelled';

    /**
     * Agent encountered an error.
     */
    case Error = 'error';
}
