<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent\Memory;

use JayI\Cortex\Plugins\Chat\Messages\Message;
use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderContract;

interface MemoryContract
{
    /**
     * Add a message to memory.
     */
    public function add(Message $message): void;

    /**
     * Add multiple messages to memory.
     */
    public function addMany(MessageCollection $messages): void;

    /**
     * Get messages to include in context.
     */
    public function messages(): MessageCollection;

    /**
     * Clear all memory.
     */
    public function clear(): void;

    /**
     * Get token count of current memory.
     */
    public function tokenCount(ProviderContract $provider): int;

    /**
     * Check if memory is empty.
     */
    public function isEmpty(): bool;

    /**
     * Get the number of messages in memory.
     */
    public function count(): int;
}
