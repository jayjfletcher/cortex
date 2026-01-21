<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent;

use JayI\Cortex\Plugins\Chat\ChatResponse;
use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;
use JayI\Cortex\Plugins\Chat\Usage;
use Spatie\LaravelData\Data;

class AgentResponse extends Data
{
    /**
     * @param  array<int, AgentIteration>  $iterations
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $content,
        public MessageCollection $messages,
        public int $iterationCount,
        public array $iterations,
        public Usage $totalUsage,
        public AgentStopReason $stopReason,
        public ?ChatResponse $finalResponse = null,
        public array $metadata = [],
    ) {}

    /**
     * Create a successful response.
     *
     * @param  array<int, AgentIteration>  $iterations
     */
    public static function success(
        string $content,
        MessageCollection $messages,
        array $iterations,
        Usage $totalUsage,
        ?ChatResponse $finalResponse = null,
    ): static {
        return new static(
            content: $content,
            messages: $messages,
            iterationCount: count($iterations),
            iterations: $iterations,
            totalUsage: $totalUsage,
            stopReason: AgentStopReason::Completed,
            finalResponse: $finalResponse,
        );
    }

    /**
     * Create a response for max iterations reached.
     *
     * @param  array<int, AgentIteration>  $iterations
     */
    public static function maxIterations(
        string $content,
        MessageCollection $messages,
        array $iterations,
        Usage $totalUsage,
        ?ChatResponse $finalResponse = null,
    ): static {
        return new static(
            content: $content,
            messages: $messages,
            iterationCount: count($iterations),
            iterations: $iterations,
            totalUsage: $totalUsage,
            stopReason: AgentStopReason::MaxIterations,
            finalResponse: $finalResponse,
        );
    }

    /**
     * Create a response for tool stop signal.
     *
     * @param  array<int, AgentIteration>  $iterations
     */
    public static function toolStopped(
        string $content,
        MessageCollection $messages,
        array $iterations,
        Usage $totalUsage,
        ?ChatResponse $finalResponse = null,
    ): static {
        return new static(
            content: $content,
            messages: $messages,
            iterationCount: count($iterations),
            iterations: $iterations,
            totalUsage: $totalUsage,
            stopReason: AgentStopReason::ToolStopped,
            finalResponse: $finalResponse,
        );
    }

    /**
     * Check if the agent completed successfully.
     */
    public function isComplete(): bool
    {
        return $this->stopReason === AgentStopReason::Completed;
    }

    /**
     * Check if max iterations was reached.
     */
    public function hitMaxIterations(): bool
    {
        return $this->stopReason === AgentStopReason::MaxIterations;
    }

    /**
     * Get the last iteration.
     */
    public function lastIteration(): ?AgentIteration
    {
        return $this->iterations[count($this->iterations) - 1] ?? null;
    }

    /**
     * Get all tool calls made during the run.
     *
     * @return array<int, array{tool: string, input: array<string, mixed>, output: mixed}>
     */
    public function toolCalls(): array
    {
        $calls = [];
        foreach ($this->iterations as $iteration) {
            foreach ($iteration->toolCalls as $call) {
                $calls[] = $call;
            }
        }

        return $calls;
    }
}
