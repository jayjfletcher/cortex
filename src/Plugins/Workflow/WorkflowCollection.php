<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Workflow;

use ArrayIterator;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use IteratorAggregate;
use JayI\Cortex\Plugins\Workflow\Contracts\WorkflowContract;
use Traversable;

/**
 * @implements IteratorAggregate<string, WorkflowContract>
 * @implements Arrayable<string, WorkflowContract>
 */
class WorkflowCollection implements Arrayable, Countable, IteratorAggregate
{
    /**
     * @var array<string, WorkflowContract>
     */
    protected array $workflows = [];

    /**
     * @param  array<int|string, WorkflowContract>  $workflows
     */
    public function __construct(array $workflows = [])
    {
        foreach ($workflows as $workflow) {
            if ($workflow instanceof WorkflowContract) {
                $this->workflows[$workflow->id()] = $workflow;
            }
        }
    }

    /**
     * Create a new collection.
     *
     * @param  array<int|string, WorkflowContract>  $workflows
     */
    public static function make(array $workflows = []): static
    {
        return new static($workflows);
    }

    /**
     * Add a workflow to the collection.
     */
    public function add(WorkflowContract $workflow): static
    {
        $this->workflows[$workflow->id()] = $workflow;

        return $this;
    }

    /**
     * Remove a workflow from the collection.
     */
    public function remove(string $id): static
    {
        unset($this->workflows[$id]);

        return $this;
    }

    /**
     * Get a workflow by ID.
     */
    public function get(string $id): ?WorkflowContract
    {
        return $this->workflows[$id] ?? null;
    }

    /**
     * Check if a workflow exists.
     */
    public function has(string $id): bool
    {
        return isset($this->workflows[$id]);
    }

    /**
     * Get workflow IDs.
     *
     * @return array<int, string>
     */
    public function ids(): array
    {
        return array_keys($this->workflows);
    }

    /**
     * Get count of workflows.
     */
    public function count(): int
    {
        return count($this->workflows);
    }

    /**
     * Check if collection is empty.
     */
    public function isEmpty(): bool
    {
        return count($this->workflows) === 0;
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
     * @return Traversable<string, WorkflowContract>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->workflows);
    }

    /**
     * Convert to array.
     *
     * @return array<string, WorkflowContract>
     */
    public function toArray(): array
    {
        return $this->workflows;
    }

    /**
     * Get all workflows.
     *
     * @return array<string, WorkflowContract>
     */
    public function all(): array
    {
        return $this->workflows;
    }

    /**
     * Get only the specified workflows.
     *
     * @param  array<int, string>  $ids
     */
    public function only(array $ids): static
    {
        return new static(array_intersect_key($this->workflows, array_flip($ids)));
    }

    /**
     * Get all workflows except the specified ones.
     *
     * @param  array<int, string>  $ids
     */
    public function except(array $ids): static
    {
        return new static(array_diff_key($this->workflows, array_flip($ids)));
    }

    /**
     * Merge with another collection.
     */
    public function merge(WorkflowCollection $other): static
    {
        $collection = new static($this->workflows);
        foreach ($other->workflows as $workflow) {
            $collection->workflows[$workflow->id()] = $workflow;
        }

        return $collection;
    }

    /**
     * Filter workflows by a callback.
     *
     * @param  callable(WorkflowContract): bool  $callback
     */
    public function filter(callable $callback): static
    {
        return new static(array_filter($this->workflows, $callback));
    }

    /**
     * Map workflows to a new array.
     *
     * @template T
     *
     * @param  callable(WorkflowContract): T  $callback
     * @return array<string, T>
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->workflows);
    }
}
