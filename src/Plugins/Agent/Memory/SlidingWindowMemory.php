<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent\Memory;

use JayI\Cortex\Plugins\Chat\MessageRole;
use JayI\Cortex\Plugins\Chat\Messages\Message;
use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderContract;

/**
 * Sliding window memory that keeps a fixed number of recent messages.
 */
class SlidingWindowMemory implements MemoryContract
{
    protected MessageCollection $messages;

    protected ?Message $systemMessage = null;

    public function __construct(
        protected int $windowSize = 10,
        protected bool $keepSystemMessage = true,
    ) {
        $this->messages = MessageCollection::make();
    }

    /**
     * {@inheritdoc}
     */
    public function add(Message $message): void
    {
        // Track system message separately if configured
        if ($this->keepSystemMessage && $message->role === MessageRole::System) {
            $this->systemMessage = $message;

            return;
        }

        $this->messages->add($message);
        $this->truncate();
    }

    /**
     * {@inheritdoc}
     */
    public function addMany(MessageCollection $messages): void
    {
        foreach ($messages as $message) {
            $this->add($message);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function messages(): MessageCollection
    {
        $result = MessageCollection::make();

        // Add system message first if present
        if ($this->systemMessage !== null) {
            $result->add($this->systemMessage);
        }

        // Add window messages
        foreach ($this->messages as $message) {
            $result->add($message);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->messages = MessageCollection::make();
        $this->systemMessage = null;
    }

    /**
     * {@inheritdoc}
     */
    public function tokenCount(ProviderContract $provider): int
    {
        return $this->messages()->estimateTokens($provider);
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty(): bool
    {
        return $this->messages->isEmpty() && $this->systemMessage === null;
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        $count = $this->messages->count();
        if ($this->systemMessage !== null) {
            $count++;
        }

        return $count;
    }

    /**
     * Truncate messages to window size.
     */
    protected function truncate(): void
    {
        $all = $this->messages->all();
        $count = count($all);

        if ($count > $this->windowSize) {
            $this->messages = MessageCollection::make(
                array_slice($all, $count - $this->windowSize)
            );
        }
    }
}
