<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\ContextManager\Contracts;

use JayI\Cortex\Plugins\ContextManager\Data\ContextMessage;
use JayI\Cortex\Plugins\ContextManager\Data\ContextWindow;

interface ContextManagerContract
{
    /**
     * Create a new context window.
     */
    public function create(int $maxTokens, ?string $systemPrompt = null): ContextWindow;

    /**
     * Add a message to the context, managing overflow automatically.
     */
    public function addMessage(ContextWindow $window, ContextMessage $message): ContextWindow;

    /**
     * Add multiple messages to the context.
     *
     * @param  array<int, ContextMessage>  $messages
     */
    public function addMessages(ContextWindow $window, array $messages): ContextWindow;

    /**
     * Ensure context fits within token limits.
     */
    public function fit(ContextWindow $window, ?int $targetTokens = null): ContextWindow;

    /**
     * Get estimated response tokens available.
     */
    public function getResponseTokenBudget(ContextWindow $window, int $reserveTokens = 1000): int;

    /**
     * Set the context reduction strategy.
     */
    public function setStrategy(ContextStrategyContract $strategy): self;

    /**
     * Get the current strategy.
     */
    public function getStrategy(): ContextStrategyContract;
}
