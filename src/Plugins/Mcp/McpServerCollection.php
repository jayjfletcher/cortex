<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Mcp;

use ArrayIterator;
use Countable;
use Illuminate\Contracts\Support\Arrayable;
use IteratorAggregate;
use JayI\Cortex\Plugins\Mcp\Contracts\McpServerContract;
use JayI\Cortex\Plugins\Tool\ToolCollection;
use Traversable;

/**
 * @implements IteratorAggregate<string, McpServerContract|string>
 * @implements Arrayable<string, McpServerContract|string>
 */
class McpServerCollection implements Arrayable, Countable, IteratorAggregate
{
    /**
     * @var array<string, McpServerContract|string>
     */
    protected array $servers = [];

    /**
     * @param  array<int|string, McpServerContract|string>  $servers
     */
    public function __construct(array $servers = [])
    {
        foreach ($servers as $server) {
            if ($server instanceof McpServerContract) {
                $this->servers[$server->id()] = $server;
            } elseif (is_string($server)) {
                $this->servers[$server] = $server;
            }
        }
    }

    /**
     * Create a new collection.
     *
     * @param  array<int|string, McpServerContract|string>  $servers
     */
    public static function make(array $servers = []): static
    {
        return new static($servers);
    }

    /**
     * Add a server to the collection.
     */
    public function add(McpServerContract|string $server): static
    {
        if ($server instanceof McpServerContract) {
            $this->servers[$server->id()] = $server;
        } else {
            $this->servers[$server] = $server;
        }

        return $this;
    }

    /**
     * Remove a server from the collection.
     */
    public function remove(string $id): static
    {
        unset($this->servers[$id]);

        return $this;
    }

    /**
     * Get a server by ID.
     */
    public function get(string $id): McpServerContract|string|null
    {
        return $this->servers[$id] ?? null;
    }

    /**
     * Check if a server exists.
     */
    public function has(string $id): bool
    {
        return isset($this->servers[$id]);
    }

    /**
     * Get server IDs.
     *
     * @return array<int, string>
     */
    public function ids(): array
    {
        return array_keys($this->servers);
    }

    /**
     * Get count of servers.
     */
    public function count(): int
    {
        return count($this->servers);
    }

    /**
     * Check if collection is empty.
     */
    public function isEmpty(): bool
    {
        return count($this->servers) === 0;
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
     * @return Traversable<string, McpServerContract|string>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->servers);
    }

    /**
     * Convert to array.
     *
     * @return array<string, McpServerContract|string>
     */
    public function toArray(): array
    {
        return $this->servers;
    }

    /**
     * Merge with another collection.
     */
    public function merge(McpServerCollection $other): static
    {
        $collection = new static($this->servers);
        foreach ($other->servers as $id => $server) {
            $collection->servers[$id] = $server;
        }

        return $collection;
    }

    /**
     * Get only resolved server instances (excludes strings).
     *
     * @return array<string, McpServerContract>
     */
    public function resolved(): array
    {
        return array_filter(
            $this->servers,
            fn ($server) => $server instanceof McpServerContract
        );
    }

    /**
     * Get only unresolved server references (strings only).
     *
     * @return array<string, string>
     */
    public function unresolved(): array
    {
        return array_filter(
            $this->servers,
            fn ($server) => is_string($server)
        );
    }

    /**
     * Collect tools from all resolved MCP servers.
     *
     * This method iterates through all resolved McpServerContract instances
     * and collects their tools into a single ToolCollection.
     */
    public function toTools(): ToolCollection
    {
        $tools = ToolCollection::make([]);

        foreach ($this->resolved() as $server) {
            $serverTools = $server->tools();
            $tools = $tools->merge($serverTools);
        }

        return $tools;
    }

    /**
     * Get all servers.
     *
     * @return array<string, McpServerContract|string>
     */
    public function all(): array
    {
        return $this->servers;
    }

    /**
     * Get only the specified servers.
     *
     * @param  array<int, string>  $ids
     */
    public function only(array $ids): static
    {
        return new static(array_intersect_key($this->servers, array_flip($ids)));
    }

    /**
     * Get all servers except the specified ones.
     *
     * @param  array<int, string>  $ids
     */
    public function except(array $ids): static
    {
        return new static(array_diff_key($this->servers, array_flip($ids)));
    }

    /**
     * Filter servers by a callback.
     *
     * @param  callable(McpServerContract|string, string): bool  $callback
     */
    public function filter(callable $callback): static
    {
        return new static(array_filter($this->servers, $callback, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * Map servers to a new array.
     *
     * @template T
     *
     * @param  callable(McpServerContract|string): T  $callback
     * @return array<string, T>
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->servers);
    }

    /**
     * Connect all resolved servers.
     */
    public function connectAll(): void
    {
        foreach ($this->resolved() as $server) {
            if (! $server->isConnected()) {
                $server->connect();
            }
        }
    }

    /**
     * Disconnect all resolved servers.
     */
    public function disconnectAll(): void
    {
        foreach ($this->resolved() as $server) {
            if ($server->isConnected()) {
                $server->disconnect();
            }
        }
    }
}
