<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Prompt;

use ArrayIterator;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use IteratorAggregate;
use JayI\Cortex\Plugins\Prompt\Contracts\PromptContract;
use Traversable;

/**
 * @implements IteratorAggregate<string, PromptContract>
 * @implements Arrayable<string, PromptContract>
 */
class PromptCollection implements Arrayable, Countable, IteratorAggregate
{
    /**
     * @var array<string, PromptContract>
     */
    protected array $prompts = [];

    /**
     * @param  array<int|string, PromptContract>  $prompts
     */
    public function __construct(array $prompts = [])
    {
        foreach ($prompts as $prompt) {
            if ($prompt instanceof PromptContract) {
                $this->prompts[$prompt->id()] = $prompt;
            }
        }
    }

    /**
     * Create a new collection.
     *
     * @param  array<int|string, PromptContract>  $prompts
     */
    public static function make(array $prompts = []): static
    {
        return new static($prompts);
    }

    /**
     * Add a prompt to the collection.
     */
    public function add(PromptContract $prompt): static
    {
        $this->prompts[$prompt->id()] = $prompt;

        return $this;
    }

    /**
     * Remove a prompt from the collection.
     */
    public function remove(string $id): static
    {
        unset($this->prompts[$id]);

        return $this;
    }

    /**
     * Get a prompt by ID.
     */
    public function get(string $id): ?PromptContract
    {
        return $this->prompts[$id] ?? null;
    }

    /**
     * Check if a prompt exists.
     */
    public function has(string $id): bool
    {
        return isset($this->prompts[$id]);
    }

    /**
     * Get prompt IDs.
     *
     * @return array<int, string>
     */
    public function ids(): array
    {
        return array_keys($this->prompts);
    }

    /**
     * Get count of prompts.
     */
    public function count(): int
    {
        return count($this->prompts);
    }

    /**
     * Check if collection is empty.
     */
    public function isEmpty(): bool
    {
        return count($this->prompts) === 0;
    }

    /**
     * Check if collection is not empty.
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Get iterator.
     *
     * @return Traversable<string, PromptContract>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->prompts);
    }

    /**
     * Convert to array.
     *
     * @return array<string, PromptContract>
     */
    public function toArray(): array
    {
        return $this->prompts;
    }

    /**
     * Get all prompts.
     *
     * @return array<string, PromptContract>
     */
    public function all(): array
    {
        return $this->prompts;
    }

    /**
     * Get only the specified prompts.
     *
     * @param  array<int, string>  $ids
     */
    public function only(array $ids): static
    {
        return new static(array_intersect_key($this->prompts, array_flip($ids)));
    }

    /**
     * Get all prompts except the specified ones.
     *
     * @param  array<int, string>  $ids
     */
    public function except(array $ids): static
    {
        return new static(array_diff_key($this->prompts, array_flip($ids)));
    }

    /**
     * Merge with another collection.
     */
    public function merge(PromptCollection $other): static
    {
        $collection = new static($this->prompts);
        foreach ($other->prompts as $prompt) {
            $collection->prompts[$prompt->id()] = $prompt;
        }

        return $collection;
    }

    /**
     * Filter prompts by a callback.
     *
     * @param  callable(PromptContract): bool  $callback
     */
    public function filter(callable $callback): static
    {
        return new static(array_filter($this->prompts, $callback));
    }

    /**
     * Map prompts to a new array.
     *
     * @template T
     *
     * @param  callable(PromptContract): T  $callback
     * @return array<string, T>
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->prompts);
    }
}
