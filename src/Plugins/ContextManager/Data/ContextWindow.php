<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\ContextManager\Data;

use Spatie\LaravelData\Data;

/**
 * Represents a context window with messages and token tracking.
 */
class ContextWindow extends Data
{
    /**
     * @param  array<int, ContextMessage>  $messages
     */
    public function __construct(
        public readonly array $messages,
        public readonly int $totalTokens,
        public readonly int $maxTokens,
        public readonly ?string $systemPrompt = null,
        public readonly int $systemPromptTokens = 0,
    ) {}

    /**
     * Get available tokens for new content.
     */
    public function availableTokens(): int
    {
        return max(0, $this->maxTokens - $this->totalTokens);
    }

    /**
     * Get utilization as a percentage.
     */
    public function utilization(): float
    {
        if ($this->maxTokens === 0) {
            return 0.0;
        }

        return ($this->totalTokens / $this->maxTokens) * 100;
    }

    /**
     * Check if context is at or near capacity.
     */
    public function isNearCapacity(float $threshold = 90.0): bool
    {
        return $this->utilization() >= $threshold;
    }

    /**
     * Get message count.
     */
    public function messageCount(): int
    {
        return count($this->messages);
    }

    /**
     * Create a new context window.
     */
    public static function create(int $maxTokens, ?string $systemPrompt = null): self
    {
        $systemTokens = $systemPrompt !== null
            ? self::estimateTokens($systemPrompt)
            : 0;

        return new self(
            messages: [],
            totalTokens: $systemTokens,
            maxTokens: $maxTokens,
            systemPrompt: $systemPrompt,
            systemPromptTokens: $systemTokens,
        );
    }

    /**
     * Add a message to the context.
     */
    public function addMessage(ContextMessage $message): self
    {
        $messages = [...$this->messages, $message];

        return new self(
            messages: $messages,
            totalTokens: $this->totalTokens + $message->tokens,
            maxTokens: $this->maxTokens,
            systemPrompt: $this->systemPrompt,
            systemPromptTokens: $this->systemPromptTokens,
        );
    }

    /**
     * Replace messages with a new set.
     *
     * @param  array<int, ContextMessage>  $messages
     */
    public function withMessages(array $messages): self
    {
        $totalTokens = $this->systemPromptTokens;
        foreach ($messages as $message) {
            $totalTokens += $message->tokens;
        }

        return new self(
            messages: $messages,
            totalTokens: $totalTokens,
            maxTokens: $this->maxTokens,
            systemPrompt: $this->systemPrompt,
            systemPromptTokens: $this->systemPromptTokens,
        );
    }

    /**
     * Estimate token count for text (rough approximation).
     */
    public static function estimateTokens(string $text): int
    {
        // Rough estimate: ~4 characters per token
        return (int) ceil(mb_strlen($text) / 4);
    }
}
