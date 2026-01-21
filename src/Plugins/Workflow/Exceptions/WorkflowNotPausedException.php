<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow\Exceptions;

use Exception;
use JayI\Cortex\Plugins\Workflow\WorkflowStatus;

class WorkflowNotPausedException extends Exception
{
    public static function forRunId(string $runId): self
    {
        return new self("Workflow run '{$runId}' is not in a paused state and cannot be resumed.");
    }

    public static function withStatus(string $runId, WorkflowStatus $status): self
    {
        return new self("Workflow run '{$runId}' is in '{$status->value}' state and cannot be resumed.");
    }
}
