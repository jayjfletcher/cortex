<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Chat\Messages;

use ArrayIterator;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use IteratorAggregate;
use JayI\Cortex\Plugins\Chat\MessageRole;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderContract;
use Traversable;

/**
 * @implements IteratorAggregate<int, Message>
 * @implements Arrayable<int, array<string, mixed>>
 */
class MessageCollection implements Arrayable, Countable, IteratorAggregate
{
    /**
     * @var array<int, Message>
     */
    protected array $messages = [];

    /**
     * @param  array<int, Message>  $messages
     */
    public function __construct(array $messages = [])
    {
        $this->messages = $messages;
    }

    /**
     * Create a new collection.
     *
     * @param  array<int, Message>  $messages
     */
    public static function make(array $messages = []): static
    {
        return new static($messages);
    }

    /**
     * Add a message to the collection.
     */
    public function add(Message $message): static
    {
        $this->messages[] = $message;

        return $this;
    }

    /**
     * Push one or more messages.
     */
    public function push(Message ...$messages): static
    {
        foreach ($messages as $message) {
            $this->messages[] = $message;
        }

        return $this;
    }

    /**
     * Prepend a message to the collection.
     */
    public function prepend(Message $message): static
    {
        array_unshift($this->messages, $message);

        return $this;
    }

    /**
     * Add a system message.
     */
    public function system(string $content): static
    {
        return $this->add(Message::system($content));
    }

    /**
     * Add a user message.
     *
     * @param  string|Content|array<int, Content>  $content
     */
    public function user(string|Content|array $content): static
    {
        return $this->add(Message::user($content));
    }

    /**
     * Add an assistant message.
     *
     * @param  string|Content|array<int, Content>  $content
     */
    public function assistant(string|Content|array $content): static
    {
        return $this->add(Message::assistant($content));
    }

    /**
     * Get the last message.
     */
    public function last(): ?Message
    {
        $count = count($this->messages);

        return $count > 0 ? $this->messages[$count - 1] : null;
    }

    /**
     * Get the first message.
     */
    public function first(): ?Message
    {
        return $this->messages[0] ?? null;
    }

    /**
     * Filter messages by role.
     */
    public function byRole(MessageRole $role): static
    {
        return new static(array_values(array_filter(
            $this->messages,
            fn (Message $m) => $m->role === $role
        )));
    }

    /**
     * Get messages without system messages.
     */
    public function withoutSystem(): static
    {
        return new static(array_values(array_filter(
            $this->messages,
            fn (Message $m) => $m->role !== MessageRole::System
        )));
    }

    /**
     * Estimate token count for the messages.
     */
    public function estimateTokens(ProviderContract $provider): int
    {
        $total = 0;
        foreach ($this->messages as $message) {
            $total += $provider->countTokens($message);
        }

        return $total;
    }

    /**
     * Truncate messages to fit within token limit.
     */
    public function truncateToTokens(int $maxTokens, ProviderContract $provider): static
    {
        $messages = [];
        $tokenCount = 0;

        // Always keep system messages
        foreach ($this->messages as $message) {
            if ($message->role === MessageRole::System) {
                $messages[] = $message;
                $tokenCount += $provider->countTokens($message);
            }
        }

        // Add messages from end until we hit the limit
        $nonSystemMessages = array_values(array_filter(
            $this->messages,
            fn (Message $m) => $m->role !== MessageRole::System
        ));

        for ($i = count($nonSystemMessages) - 1; $i >= 0; $i--) {
            $message = $nonSystemMessages[$i];
            $messageTokens = $provider->countTokens($message);

            if ($tokenCount + $messageTokens <= $maxTokens) {
                array_unshift($messages, $message);
                $tokenCount += $messageTokens;
            } else {
                break;
            }
        }

        // Sort to restore original order (system first, then chronological)
        usort($messages, function ($a, $b) {
            if ($a->role === MessageRole::System && $b->role !== MessageRole::System) {
                return -1;
            }
            if ($b->role === MessageRole::System && $a->role !== MessageRole::System) {
                return 1;
            }

            return 0;
        });

        return new static($messages);
    }

    /**
     * Get the number of messages.
     */
    public function count(): int
    {
        return count($this->messages);
    }

    /**
     * Check if the collection is empty.
     */
    public function isEmpty(): bool
    {
        return count($this->messages) === 0;
    }

    /**
     * Check if the collection is not empty.
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Get an iterator for the messages.
     *
     * @return Traversable<int, Message>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->messages);
    }

    /**
     * Convert to array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(fn (Message $m) => $m->toArray(), $this->messages);
    }

    /**
     * Get all messages.
     *
     * @return array<int, Message>
     */
    public function all(): array
    {
        return $this->messages;
    }
}
