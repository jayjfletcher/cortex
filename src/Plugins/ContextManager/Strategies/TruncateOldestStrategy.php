<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\ContextManager\Strategies;

use JayI\Cortex\Plugins\ContextManager\Contracts\ContextStrategyContract;
use JayI\Cortex\Plugins\ContextManager\Data\ContextWindow;

/**
 * Strategy that removes oldest messages first, preserving pinned messages.
 */
class TruncateOldestStrategy implements ContextStrategyContract
{
    public function __construct(
        protected bool $preservePinned = true,
        protected int $keepMinMessages = 2,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return 'truncate-oldest';
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

        // Separate pinned and regular messages
        $pinned = [];
        $regular = [];

        foreach ($messages as $message) {
            if ($this->preservePinned && $message->pinned) {
                $pinned[] = $message;
            } else {
                $regular[] = $message;
            }
        }

        // Calculate tokens needed for pinned messages
        $pinnedTokens = array_sum(array_map(fn ($m) => $m->tokens, $pinned));
        $availableForRegular = $targetTokens - $window->systemPromptTokens - $pinnedTokens;

        if ($availableForRegular <= 0) {
            // Can only fit pinned messages
            return $window->withMessages($pinned);
        }

        // Remove oldest messages until we fit
        $keptRegular = [];
        $regularTokens = 0;

        // Process from newest to oldest (reverse order)
        $reversedRegular = array_reverse($regular);

        foreach ($reversedRegular as $message) {
            if ($regularTokens + $message->tokens <= $availableForRegular) {
                array_unshift($keptRegular, $message);
                $regularTokens += $message->tokens;
            }

            // Ensure we keep minimum messages
            if (count($keptRegular) >= count($regular) - $this->keepMinMessages) {
                break;
            }
        }

        // Merge pinned and kept regular messages, maintaining chronological order
        $finalMessages = [];
        $regularIndex = 0;
        $pinnedIndex = 0;

        // Sort by timestamp
        $allKept = array_merge($pinned, $keptRegular);
        usort($allKept, fn ($a, $b) => $a->timestamp <=> $b->timestamp);

        return $window->withMessages($allKept);
    }

    /**
     * {@inheritdoc}
     */
    public function supports(ContextWindow $window): bool
    {
        return true;
    }
}
