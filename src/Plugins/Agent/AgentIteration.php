<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent;

use JayI\Cortex\Plugins\Chat\ChatResponse;
use JayI\Cortex\Plugins\Chat\Usage;
use Spatie\LaravelData\Data;

class AgentIteration extends Data
{
    /**
     * @param  array<int, array{tool: string, input: array<string, mixed>, output: mixed}>  $toolCalls
     */
    public function __construct(
        public int $index,
        public ChatResponse $response,
        public array $toolCalls,
        public Usage $usage,
        public float $duration,
    ) {}

    /**
     * Check if this iteration had tool calls.
     */
    public function hasToolCalls(): bool
    {
        return count($this->toolCalls) > 0;
    }

    /**
     * Get the text content from this iteration.
     */
    public function content(): string
    {
        return $this->response->content();
    }
}
