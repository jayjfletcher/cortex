<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent;

enum AgentRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isComplete(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }

    public function isTerminal(): bool
    {
        return $this->isComplete();
    }

    public function isRunning(): bool
    {
        return $this === self::Running;
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isSuccessful(): bool
    {
        return $this === self::Completed;
    }
}
