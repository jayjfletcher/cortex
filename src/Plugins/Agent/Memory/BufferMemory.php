<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent\Memory;

use JayI\Cortex\Plugins\Chat\Messages\Message;
use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderContract;

/**
 * Simple buffer memory that keeps all messages.
 */
class BufferMemory implements MemoryContract
{
    protected MessageCollection $messages;

    public function __construct()
    {
        $this->messages = MessageCollection::make();
    }

    /**
     * {@inheritdoc}
     */
    public function add(Message $message): void
    {
        $this->messages->add($message);
    }

    /**
     * {@inheritdoc}
     */
    public function addMany(MessageCollection $messages): void
    {
        foreach ($messages as $message) {
            $this->messages->add($message);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function messages(): MessageCollection
    {
        return $this->messages;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->messages = MessageCollection::make();
    }

    /**
     * {@inheritdoc}
     */
    public function tokenCount(ProviderContract $provider): int
    {
        return $this->messages->estimateTokens($provider);
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty(): bool
    {
        return $this->messages->isEmpty();
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return $this->messages->count();
    }
}
