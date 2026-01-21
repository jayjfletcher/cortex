<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\ContextManager\Strategies;

use JayI\Cortex\Plugins\ContextManager\Contracts\ContextStrategyContract;
use JayI\Cortex\Plugins\ContextManager\Data\ContextMessage;
use JayI\Cortex\Plugins\ContextManager\Data\ContextWindow;

/**
 * Strategy that prioritizes messages by importance score.
 */
class ImportanceStrategy implements ContextStrategyContract
{
    public function __construct(
        protected float $recencyWeight = 0.3,
        protected int $keepMinMessages = 2,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return 'importance';
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

        // Calculate composite score for each message
        $scored = [];
        $messageCount = count($messages);

        foreach ($messages as $index => $message) {
            $recencyScore = ($index + 1) / $messageCount; // 0 to 1, newer = higher
            $compositeScore = $this->calculateScore($message, $recencyScore);

            $scored[] = [
                'message' => $message,
                'score' => $compositeScore,
                'index' => $index,
            ];
        }

        // Sort by score descending
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        // Keep messages until we exceed target
        $kept = [];
        $currentTokens = $window->systemPromptTokens;
        $availableTokens = $targetTokens;

        // Always include pinned messages first
        foreach ($scored as $item) {
            if ($item['message']->pinned) {
                $kept[] = $item;
                $currentTokens += $item['message']->tokens;
            }
        }

        // Add non-pinned messages by score
        foreach ($scored as $item) {
            if ($item['message']->pinned) {
                continue;
            }

            if ($currentTokens + $item['message']->tokens <= $availableTokens) {
                $kept[] = $item;
                $currentTokens += $item['message']->tokens;
            }
        }

        // Ensure minimum messages
        if (count($kept) < $this->keepMinMessages && count($messages) >= $this->keepMinMessages) {
            // Force add from the end (most recent)
            $kept = [];
            $currentTokens = $window->systemPromptTokens;

            for ($i = $messageCount - 1; $i >= 0 && count($kept) < $this->keepMinMessages; $i--) {
                $kept[] = ['message' => $messages[$i], 'index' => $i];
                $currentTokens += $messages[$i]->tokens;
            }
        }

        // Sort by original index to maintain conversation order
        usort($kept, fn ($a, $b) => $a['index'] <=> $b['index']);

        return $window->withMessages(array_map(fn ($item) => $item['message'], $kept));
    }

    /**
     * {@inheritdoc}
     */
    public function supports(ContextWindow $window): bool
    {
        return true;
    }

    /**
     * Calculate composite score for a message.
     */
    protected function calculateScore(ContextMessage $message, float $recencyScore): float
    {
        // Pinned messages get maximum score
        if ($message->pinned) {
            return 2.0;
        }

        // Composite: importance * (1 - recencyWeight) + recency * recencyWeight
        $importanceComponent = $message->importance * (1 - $this->recencyWeight);
        $recencyComponent = $recencyScore * $this->recencyWeight;

        return $importanceComponent + $recencyComponent;
    }

    /**
     * Set the recency weight.
     */
    public function setRecencyWeight(float $weight): self
    {
        $this->recencyWeight = max(0.0, min(1.0, $weight));

        return $this;
    }
}
