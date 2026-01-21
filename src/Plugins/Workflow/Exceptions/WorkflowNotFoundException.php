<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow\Exceptions;

use Exception;

class WorkflowNotFoundException extends Exception
{
    public static function forRunId(string $runId): self
    {
        return new self("Workflow run '{$runId}' not found.");
    }

    public static function forWorkflowId(string $workflowId): self
    {
        return new self("Workflow '{$workflowId}' not found.");
    }
}
