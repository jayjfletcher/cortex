<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\ContextManager\Contracts;

use JayI\Cortex\Plugins\ContextManager\Data\ContextWindow;

interface ContextStrategyContract
{
    /**
     * Get the strategy identifier.
     */
    public function id(): string;

    /**
     * Reduce context to fit within token limits.
     */
    public function reduce(ContextWindow $window, int $targetTokens): ContextWindow;

    /**
     * Check if the strategy can handle this context.
     */
    public function supports(ContextWindow $window): bool;
}
