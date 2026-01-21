<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\ContextManager;

use JayI\Cortex\Plugins\ContextManager\Contracts\ContextManagerContract;
use JayI\Cortex\Plugins\ContextManager\Contracts\ContextStrategyContract;
use JayI\Cortex\Plugins\ContextManager\Data\ContextMessage;
use JayI\Cortex\Plugins\ContextManager\Data\ContextWindow;
use JayI\Cortex\Plugins\ContextManager\Strategies\TruncateOldestStrategy;

/**
 * Manages context windows for LLM conversations.
 */
class ContextManager implements ContextManagerContract
{
    protected ContextStrategyContract $strategy;

    protected float $autoReduceThreshold = 0.9;

    public function __construct(?ContextStrategyContract $strategy = null)
    {
        $this->strategy = $strategy ?? new TruncateOldestStrategy;
    }

    /**
     * {@inheritdoc}
     */
    public function create(int $maxTokens, ?string $systemPrompt = null): ContextWindow
    {
        return ContextWindow::create($maxTokens, $systemPrompt);
    }

    /**
     * {@inheritdoc}
     */
    public function addMessage(ContextWindow $window, ContextMessage $message): ContextWindow
    {
        $newWindow = $window->addMessage($message);

        // Auto-reduce if near capacity
        if ($newWindow->utilization() > ($this->autoReduceThreshold * 100)) {
            $targetTokens = (int) ($window->maxTokens * $this->autoReduceThreshold);

            return $this->fit($newWindow, $targetTokens);
        }

        return $newWindow;
    }

    /**
     * {@inheritdoc}
     */
    public function addMessages(ContextWindow $window, array $messages): ContextWindow
    {
        $currentWindow = $window;

        foreach ($messages as $message) {
            $currentWindow = $currentWindow->addMessage($message);
        }

        // Check if we need to reduce after adding all messages
        if ($currentWindow->utilization() > ($this->autoReduceThreshold * 100)) {
            $targetTokens = (int) ($window->maxTokens * $this->autoReduceThreshold);

            return $this->fit($currentWindow, $targetTokens);
        }

        return $currentWindow;
    }

    /**
     * {@inheritdoc}
     */
    public function fit(ContextWindow $window, ?int $targetTokens = null): ContextWindow
    {
        $target = $targetTokens ?? $window->maxTokens;

        if ($window->totalTokens <= $target) {
            return $window;
        }

        return $this->strategy->reduce($window, $target);
    }

    /**
     * {@inheritdoc}
     */
    public function getResponseTokenBudget(ContextWindow $window, int $reserveTokens = 1000): int
    {
        $available = $window->availableTokens();

        return max(0, $available - $reserveTokens);
    }

    /**
     * {@inheritdoc}
     */
    public function setStrategy(ContextStrategyContract $strategy): self
    {
        $this->strategy = $strategy;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getStrategy(): ContextStrategyContract
    {
        return $this->strategy;
    }

    /**
     * Set the auto-reduce threshold (0.0 to 1.0).
     */
    public function setAutoReduceThreshold(float $threshold): self
    {
        $this->autoReduceThreshold = max(0.5, min(1.0, $threshold));

        return $this;
    }

    /**
     * Convert messages to API format.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function toApiFormat(ContextWindow $window): array
    {
        $messages = [];

        if ($window->systemPrompt !== null) {
            $messages[] = [
                'role' => 'system',
                'content' => $window->systemPrompt,
            ];
        }

        foreach ($window->messages as $message) {
            $messages[] = [
                'role' => $message->role,
                'content' => $message->content,
            ];
        }

        return $messages;
    }
}
