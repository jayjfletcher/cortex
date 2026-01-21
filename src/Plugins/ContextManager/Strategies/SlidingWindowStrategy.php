<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\ContextManager\Strategies;

use JayI\Cortex\Plugins\ContextManager\Contracts\ContextStrategyContract;
use JayI\Cortex\Plugins\ContextManager\Data\ContextWindow;

/**
 * Strategy that maintains a sliding window of the most recent messages.
 */
class SlidingWindowStrategy implements ContextStrategyContract
{
    public function __construct(
        protected int $maxMessages = 20,
        protected bool $preservePinned = true,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return 'sliding-window';
    }

    /**
     * {@inheritdoc}
     */
    public function reduce(ContextWindow $window, int $targetTokens): ContextWindow
    {
        $messages = $window->messages;

        if (empty($messages)) {
            return $window;
        }

        // Separate pinned messages
        $pinned = [];
        $regular = [];

        foreach ($messages as $message) {
            if ($this->preservePinned && $message->pinned) {
                $pinned[] = $message;
            } else {
                $regular[] = $message;
            }
        }

        // Calculate how many regular messages we can keep
        $maxRegular = $this->maxMessages - count($pinned);

        if ($maxRegular <= 0) {
            // Only pinned messages
            return $this->fitToTokens($window, $pinned, $targetTokens);
        }

        // Keep the most recent regular messages
        $keptRegular = array_slice($regular, -$maxRegular);

        // Combine and check token limits
        $allKept = array_merge($pinned, $keptRegular);

        // Sort by timestamp
        usort($allKept, fn ($a, $b) => $a->timestamp <=> $b->timestamp);

        return $this->fitToTokens($window, $allKept, $targetTokens);
    }

    /**
     * Fit messages to token limit.
     *
     * @param  array<int, \JayI\Cortex\Plugins\ContextManager\Data\ContextMessage>  $messages
     */
    protected function fitToTokens(ContextWindow $window, array $messages, int $targetTokens): ContextWindow
    {
        $availableTokens = $targetTokens - $window->systemPromptTokens;
        $currentTokens = 0;
        $kept = [];

        // Iterate from newest to oldest
        $reversed = array_reverse($messages);

        foreach ($reversed as $message) {
            if ($currentTokens + $message->tokens <= $availableTokens) {
                array_unshift($kept, $message);
                $currentTokens += $message->tokens;
            } elseif ($message->pinned) {
                // Always include pinned even if over limit
                array_unshift($kept, $message);
                $currentTokens += $message->tokens;
            }
        }

        return $window->withMessages($kept);
    }

    /**
     * {@inheritdoc}
     */
    public function supports(ContextWindow $window): bool
    {
        return true;
    }

    /**
     * Set the maximum number of messages.
     */
    public function setMaxMessages(int $max): self
    {
        $this->maxMessages = max(1, $max);

        return $this;
    }
}
