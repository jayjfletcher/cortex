<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Tool;

use ArrayIterator;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use IteratorAggregate;
use JayI\Cortex\Plugins\Tool\Contracts\ToolContract;
use Traversable;

/**
 * @implements IteratorAggregate<string, ToolContract>
 * @implements Arrayable<string, ToolContract>
 */
class ToolCollection implements Arrayable, Countable, IteratorAggregate
{
    /**
     * @var array<string, ToolContract>
     */
    protected array $tools = [];

    /**
     * @param  array<int|string, ToolContract>  $tools
     */
    public function __construct(array $tools = [])
    {
        foreach ($tools as $tool) {
            if ($tool instanceof ToolContract) {
                $this->tools[$tool->name()] = $tool;
            }
        }
    }

    /**
     * Create a new collection.
     *
     * @param  array<int|string, ToolContract>  $tools
     */
    public static function make(array $tools = []): static
    {
        return new static($tools);
    }

    /**
     * Add a tool to the collection.
     */
    public function add(ToolContract $tool): static
    {
        $this->tools[$tool->name()] = $tool;

        return $this;
    }

    /**
     * Remove a tool from the collection.
     */
    public function remove(string $name): static
    {
        unset($this->tools[$name]);

        return $this;
    }

    /**
     * Get a tool by name.
     */
    public function get(string $name): ?ToolContract
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * Find a tool by name (alias for get).
     */
    public function find(string $name): ?ToolContract
    {
        return $this->get($name);
    }

    /**
     * Check if a tool exists.
     */
    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /**
     * Get tool names.
     *
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->tools);
    }

    /**
     * Convert to tool definitions for LLM API calls.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toToolDefinitions(): array
    {
        return array_values(array_map(
            fn (ToolContract $tool) => $tool->toDefinition(),
            $this->tools
        ));
    }

    /**
     * Get count of tools.
     */
    public function count(): int
    {
        return count($this->tools);
    }

    /**
     * Check if collection is empty.
     */
    public function isEmpty(): bool
    {
        return count($this->tools) === 0;
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
     * @return Traversable<string, ToolContract>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->tools);
    }

    /**
     * Convert to array.
     *
     * @return array<string, ToolContract>
     */
    public function toArray(): array
    {
        return $this->tools;
    }

    /**
     * Merge with another collection.
     */
    public function merge(ToolCollection $other): static
    {
        $collection = new static($this->tools);
        foreach ($other->tools as $tool) {
            $collection->tools[$tool->name()] = $tool;
        }

        return $collection;
    }

    /**
     * Filter tools by a callback.
     *
     * @param  callable(ToolContract): bool  $callback
     */
    public function filter(callable $callback): static
    {
        return new static(array_filter($this->tools, $callback));
    }

    /**
     * Map tools to a new array.
     *
     * @template T
     *
     * @param  callable(ToolContract): T  $callback
     * @return array<string, T>
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->tools);
    }

    /**
     * Execute a tool by name.
     *
     * @param  array<string, mixed>  $input
     */
    public function execute(string $name, array $input, ?ToolContext $context = null): ToolResult
    {
        $tool = $this->get($name);

        if ($tool === null) {
            return ToolResult::error("Tool '{$name}' not found in collection");
        }

        return $tool->execute($input, $context ?? new ToolContext);
    }

    /**
     * Convert to Chat plugin's ToolCollection.
     */
    public function toChatToolCollection(): \JayI\Cortex\Plugins\Chat\ToolCollection
    {
        return \JayI\Cortex\Plugins\Chat\ToolCollection::make(array_values($this->tools));
    }

    /**
     * Get all tools.
     *
     * @return array<string, ToolContract>
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * Get only the specified tools.
     *
     * @param  array<int, string>  $names
     */
    public function only(array $names): static
    {
        return new static(array_intersect_key($this->tools, array_flip($names)));
    }

    /**
     * Get all tools except the specified ones.
     *
     * @param  array<int, string>  $names
     */
    public function except(array $names): static
    {
        return new static(array_diff_key($this->tools, array_flip($names)));
    }
}
