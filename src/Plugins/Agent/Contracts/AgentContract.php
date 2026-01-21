<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent\Contracts;

use JayI\Cortex\Plugins\Agent\AgentContext;
use JayI\Cortex\Plugins\Agent\AgentResponse;
use JayI\Cortex\Plugins\Agent\Memory\MemoryContract;
use JayI\Cortex\Plugins\Agent\PendingAgentRun;
use JayI\Cortex\Plugins\Tool\ToolCollection;

interface AgentContract
{
    /**
     * Get the agent's unique identifier.
     */
    public function id(): string;

    /**
     * Get the agent's name.
     */
    public function name(): string;

    /**
     * Get the agent's description.
     */
    public function description(): string;

    /**
     * Get the system prompt.
     */
    public function systemPrompt(): string;

    /**
     * Get the available tools for this agent.
     */
    public function tools(): ToolCollection;

    /**
     * Get the model to use.
     */
    public function model(): ?string;

    /**
     * Get the provider to use.
     */
    public function provider(): ?string;

    /**
     * Get the maximum iterations for the agentic loop.
     */
    public function maxIterations(): int;

    /**
     * Get the memory strategy for conversation context.
     */
    public function memory(): ?MemoryContract;

    /**
     * Run the agent with input.
     *
     * @param  string|array<string, mixed>  $input
     */
    public function run(string|array $input, ?AgentContext $context = null): AgentResponse;

    /**
     * Stream the agent's response.
     *
     * @param  string|array<string, mixed>  $input
     */
    public function stream(string|array $input, ?AgentContext $context = null): AgentResponse;

    /**
     * Run the agent asynchronously via the queue.
     *
     * @param  string|array<string, mixed>  $input
     */
    public function runAsync(string|array $input, ?AgentContext $context = null): PendingAgentRun;
}
