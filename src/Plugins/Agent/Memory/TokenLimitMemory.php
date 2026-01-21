<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent\Memory;

use JayI\Cortex\Plugins\Chat\MessageRole;
use JayI\Cortex\Plugins\Chat\Messages\Message;
use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderContract;

/**
 * Memory that truncates based on token count.
 */
class TokenLimitMemory implements MemoryContract
{
    protected MessageCollection $messages;

    protected ?Message $systemMessage = null;

    protected ?ProviderContract $provider = null;

    public function __construct(
        protected int $maxTokens = 4000,
        protected string $truncationStrategy = 'oldest', // 'oldest' or 'middle'
    ) {
        $this->messages = MessageCollection::make();
    }

    /**
     * Set the provider for token counting.
     */
    public function setProvider(ProviderContract $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function add(Message $message): void
    {
        // Track system message separately
        if ($message->role === MessageRole::System) {
            $this->systemMessage = $message;

            return;
        }

        $this->messages->add($message);

        // Truncate if provider is set
        if ($this->provider !== null) {
            $this->truncate();
        }
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

        // Add conversation messages
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
     * Truncate messages to fit within token limit.
     */
    protected function truncate(): void
    {
        if ($this->provider === null) {
            return;
        }

        // Calculate system message tokens
        $systemTokens = 0;
        if ($this->systemMessage !== null) {
            $systemTokens = $this->provider->countTokens($this->systemMessage);
        }

        $availableTokens = $this->maxTokens - $systemTokens;
        if ($availableTokens <= 0) {
            $this->messages = MessageCollection::make();

            return;
        }

        if ($this->truncationStrategy === 'oldest') {
            $this->truncateOldest($availableTokens);
        } else {
            $this->truncateMiddle($availableTokens);
        }
    }

    /**
     * Truncate oldest messages first.
     */
    protected function truncateOldest(int $availableTokens): void
    {
        if ($this->provider === null) {
            return;
        }

        $all = $this->messages->all();
        $kept = [];
        $currentTokens = 0;

        // Keep messages from newest to oldest
        for ($i = count($all) - 1; $i >= 0; $i--) {
            $messageTokens = $this->provider->countTokens($all[$i]);

            if ($currentTokens + $messageTokens <= $availableTokens) {
                array_unshift($kept, $all[$i]);
                $currentTokens += $messageTokens;
            } else {
                break;
            }
        }

        $this->messages = MessageCollection::make($kept);
    }

    /**
     * Truncate middle messages (keep first and last).
     */
    protected function truncateMiddle(int $availableTokens): void
    {
        if ($this->provider === null) {
            return;
        }

        $all = $this->messages->all();
        $count = count($all);

        if ($count <= 2) {
            return;
        }

        // Always try to keep first and last messages
        $first = $all[0];
        $last = $all[$count - 1];

        $firstTokens = $this->provider->countTokens($first);
        $lastTokens = $this->provider->countTokens($last);

        if ($firstTokens + $lastTokens > $availableTokens) {
            // Can only fit the last message
            $this->messages = MessageCollection::make([$last]);

            return;
        }

        $kept = [$first];
        $remaining = $availableTokens - $firstTokens - $lastTokens;

        // Fill in from the end
        for ($i = $count - 2; $i > 0; $i--) {
            $messageTokens = $this->provider->countTokens($all[$i]);

            if ($remaining >= $messageTokens) {
                array_splice($kept, count($kept), 0, [$all[$i]]);
                $remaining -= $messageTokens;
            } else {
                break;
            }
        }

        $kept[] = $last;
        $this->messages = MessageCollection::make($kept);
    }
}
