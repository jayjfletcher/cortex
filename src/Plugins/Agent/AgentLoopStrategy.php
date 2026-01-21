<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent;

enum AgentLoopStrategy: string
{
    /**
     * Reasoning + Acting pattern.
     * Agent reasons about the task, then acts.
     */
    case ReAct = 'react';

    /**
     * Plan first, then execute.
     * Agent creates a plan, then executes steps.
     */
    case PlanAndExecute = 'plan';

    /**
     * Simple tool loop.
     * Agent calls tools until done or max iterations.
     */
    case Simple = 'simple';

    /**
     * Custom loop implementation.
     */
    case Custom = 'custom';
}
