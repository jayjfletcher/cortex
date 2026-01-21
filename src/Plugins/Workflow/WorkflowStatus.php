<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow;

enum WorkflowStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /**
     * Check if the workflow is in a terminal state.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Completed,
            self::Failed,
            self::Cancelled,
        ], true);
    }

    /**
     * Check if the workflow can be resumed.
     */
    public function canResume(): bool
    {
        return in_array($this, [
            self::Pending,
            self::Paused,
        ], true);
    }

    /**
     * Check if the workflow is active.
     */
    public function isActive(): bool
    {
        return in_array($this, [
            self::Pending,
            self::Running,
        ], true);
    }
}
