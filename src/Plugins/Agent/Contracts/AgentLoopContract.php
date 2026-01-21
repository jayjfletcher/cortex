<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent\Contracts;

use JayI\Cortex\Plugins\Agent\AgentContext;
use JayI\Cortex\Plugins\Agent\AgentResponse;

interface AgentLoopContract
{
    /**
     * Execute the agent loop.
     *
     * @param  string|array<string, mixed>  $input
     */
    public function execute(
        AgentContract $agent,
        string|array $input,
        AgentContext $context
    ): AgentResponse;
}
