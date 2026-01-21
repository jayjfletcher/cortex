<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Chat;

use ArrayIterator;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<string, mixed>
 * @implements Arrayable<string, mixed>
 */
class ToolCollection implements Arrayable, Countable, IteratorAggregate
{
    /**
     * @var array<string, mixed>
     */
    protected array $tools = [];

    /**
     * @param  array<int|string, mixed>  $tools
     */
    public function __construct(array $tools = [])
    {
        foreach ($tools as $tool) {
            if (is_object($tool) && method_exists($tool, 'name')) {
                $this->tools[$tool->name()] = $tool;
            } elseif (is_array($tool) && isset($tool['name'])) {
                $this->tools[$tool['name']] = $tool;
            }
        }
    }

    /**
     * Create a new collection.
     *
     * @param  array<int|string, mixed>  $tools
     */
    public static function make(array $tools = []): static
    {
        return new static($tools);
    }

    /**
     * Add a tool to the collection.
     */
    public function add(mixed $tool): static
    {
        if (is_object($tool) && method_exists($tool, 'name')) {
            $this->tools[$tool->name()] = $tool;
        } elseif (is_array($tool) && isset($tool['name'])) {
            $this->tools[$tool['name']] = $tool;
        }

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
    public function get(string $name): mixed
    {
        return $this->tools[$name] ?? null;
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
        $definitions = [];
        foreach ($this->tools as $tool) {
            if (is_object($tool) && method_exists($tool, 'toDefinition')) {
                $definitions[] = $tool->toDefinition();
            } elseif (is_array($tool)) {
                $definitions[] = $tool;
            }
        }

        return $definitions;
    }

    /**
     * Get count of tools.
     */
    public function count(): int
    {
        return count($this->tools);
    }

    /**
     * Get iterator.
     *
     * @return Traversable<string, mixed>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->tools);
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
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
        foreach ($other->tools as $name => $tool) {
            $collection->tools[$name] = $tool;
        }

        return $collection;
    }
}
