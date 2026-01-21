<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Agent;

use ArrayIterator;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use IteratorAggregate;
use JayI\Cortex\Plugins\Agent\Contracts\AgentContract;
use JayI\Cortex\Plugins\Tool\ToolCollection;
use Traversable;

/**
 * @implements IteratorAggregate<string, AgentContract>
 * @implements Arrayable<string, AgentContract>
 */
class AgentCollection implements Arrayable, Countable, IteratorAggregate
{
    /**
     * @var array<string, AgentContract>
     */
    protected array $agents = [];

    /**
     * @param  array<int|string, AgentContract>  $agents
     */
    public function __construct(array $agents = [])
    {
        foreach ($agents as $agent) {
            if ($agent instanceof AgentContract) {
                $this->agents[$agent->id()] = $agent;
            }
        }
    }

    /**
     * Create a new collection.
     *
     * @param  array<int|string, AgentContract>  $agents
     */
    public static function make(array $agents = []): static
    {
        return new static($agents);
    }

    /**
     * Add an agent to the collection.
     */
    public function add(AgentContract $agent): static
    {
        $this->agents[$agent->id()] = $agent;

        return $this;
    }

    /**
     * Remove an agent from the collection.
     */
    public function remove(string $id): static
    {
        unset($this->agents[$id]);

        return $this;
    }

    /**
     * Get an agent by ID.
     */
    public function get(string $id): ?AgentContract
    {
        return $this->agents[$id] ?? null;
    }

    /**
     * Check if an agent exists.
     */
    public function has(string $id): bool
    {
        return isset($this->agents[$id]);
    }

    /**
     * Get agent IDs.
     *
     * @return array<int, string>
     */
    public function ids(): array
    {
        return array_keys($this->agents);
    }

    /**
     * Get count of agents.
     */
    public function count(): int
    {
        return count($this->agents);
    }

    /**
     * Check if collection is empty.
     */
    public function isEmpty(): bool
    {
        return count($this->agents) === 0;
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
     * @return Traversable<string, AgentContract>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->agents);
    }

    /**
     * Convert to array.
     *
     * @return array<string, AgentContract>
     */
    public function toArray(): array
    {
        return $this->agents;
    }

    /**
     * Get all agents.
     *
     * @return array<string, AgentContract>
     */
    public function all(): array
    {
        return $this->agents;
    }

    /**
     * Get only the specified agents.
     *
     * @param  array<int, string>  $ids
     */
    public function only(array $ids): static
    {
        return new static(array_intersect_key($this->agents, array_flip($ids)));
    }

    /**
     * Get all agents except the specified ones.
     *
     * @param  array<int, string>  $ids
     */
    public function except(array $ids): static
    {
        return new static(array_diff_key($this->agents, array_flip($ids)));
    }

    /**
     * Merge with another collection.
     */
    public function merge(AgentCollection $other): static
    {
        $collection = new static($this->agents);
        foreach ($other->agents as $agent) {
            $collection->agents[$agent->id()] = $agent;
        }

        return $collection;
    }

    /**
     * Filter agents by a callback.
     *
     * @param  callable(AgentContract): bool  $callback
     */
    public function filter(callable $callback): static
    {
        return new static(array_filter($this->agents, $callback));
    }

    /**
     * Map agents to a new array.
     *
     * @template T
     *
     * @param  callable(AgentContract): T  $callback
     * @return array<string, T>
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->agents);
    }

    /**
     * Convert all agents in this collection to tools.
     *
     * This allows agents to be used as tools by other agents,
     * enabling multi-agent orchestration patterns.
     */
    public function asTools(): ToolCollection
    {
        $tools = [];
        foreach ($this->agents as $agent) {
            $tools[] = AgentTool::make($agent);
        }

        return ToolCollection::make($tools);
    }
}
