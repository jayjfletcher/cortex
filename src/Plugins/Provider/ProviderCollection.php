<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Provider;

use ArrayIterator;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use IteratorAggregate;
use JayI\Cortex\Plugins\Provider\Contracts\ProviderContract;
use Traversable;

/**
 * @implements IteratorAggregate<string, ProviderContract>
 * @implements Arrayable<string, ProviderContract>
 */
class ProviderCollection implements Arrayable, Countable, IteratorAggregate
{
    /**
     * @var array<string, ProviderContract>
     */
    protected array $providers = [];

    /**
     * @param  array<string, ProviderContract>  $providers
     */
    public function __construct(array $providers = [])
    {
        foreach ($providers as $id => $provider) {
            if ($provider instanceof ProviderContract) {
                $this->providers[$id] = $provider;
            }
        }
    }

    /**
     * Create a new collection.
     *
     * @param  array<string, ProviderContract>  $providers
     */
    public static function make(array $providers = []): static
    {
        return new static($providers);
    }

    /**
     * Add a provider to the collection.
     */
    public function add(string $id, ProviderContract $provider): static
    {
        $this->providers[$id] = $provider;

        return $this;
    }

    /**
     * Remove a provider from the collection.
     */
    public function remove(string $id): static
    {
        unset($this->providers[$id]);

        return $this;
    }

    /**
     * Get a provider by ID.
     */
    public function get(string $id): ?ProviderContract
    {
        return $this->providers[$id] ?? null;
    }

    /**
     * Check if a provider exists.
     */
    public function has(string $id): bool
    {
        return isset($this->providers[$id]);
    }

    /**
     * Get provider IDs.
     *
     * @return array<int, string>
     */
    public function ids(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Get count of providers.
     */
    public function count(): int
    {
        return count($this->providers);
    }

    /**
     * Check if collection is empty.
     */
    public function isEmpty(): bool
    {
        return count($this->providers) === 0;
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
     * @return Traversable<string, ProviderContract>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->providers);
    }

    /**
     * Convert to array.
     *
     * @return array<string, ProviderContract>
     */
    public function toArray(): array
    {
        return $this->providers;
    }

    /**
     * Get all providers.
     *
     * @return array<string, ProviderContract>
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * Get only the specified providers.
     *
     * @param  array<int, string>  $ids
     */
    public function only(array $ids): static
    {
        return new static(array_intersect_key($this->providers, array_flip($ids)));
    }

    /**
     * Get all providers except the specified ones.
     *
     * @param  array<int, string>  $ids
     */
    public function except(array $ids): static
    {
        return new static(array_diff_key($this->providers, array_flip($ids)));
    }

    /**
     * Merge with another collection.
     */
    public function merge(ProviderCollection $other): static
    {
        $collection = new static($this->providers);
        foreach ($other->providers as $id => $provider) {
            $collection->providers[$id] = $provider;
        }

        return $collection;
    }

    /**
     * Filter providers by a callback.
     *
     * @param  callable(ProviderContract, string): bool  $callback
     */
    public function filter(callable $callback): static
    {
        return new static(array_filter($this->providers, $callback, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * Map providers to a new array.
     *
     * @template T
     *
     * @param  callable(ProviderContract, string): T  $callback
     * @return array<string, T>
     */
    public function map(callable $callback): array
    {
        $result = [];
        foreach ($this->providers as $id => $provider) {
            $result[$id] = $callback($provider, $id);
        }

        return $result;
    }
}
