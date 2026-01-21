<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Mcp;

use JayI\Cortex\Exceptions\McpException;
use JayI\Cortex\Plugins\Mcp\Contracts\McpRegistryContract;
use JayI\Cortex\Plugins\Mcp\Contracts\McpServerContract;

class McpRegistry implements McpRegistryContract
{
    protected McpServerCollection $servers;

    public function __construct()
    {
        $this->servers = McpServerCollection::make([]);
    }

    /**
     * {@inheritdoc}
     */
    public function register(McpServerContract $server): void
    {
        $this->servers = $this->servers->add($server);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $id): McpServerContract
    {
        if (! $this->has($id)) {
            throw McpException::serverNotFound($id);
        }

        return $this->servers->get($id);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool
    {
        return $this->servers->has($id);
    }

    /**
     * {@inheritdoc}
     */
    public function all(): McpServerCollection
    {
        return $this->servers;
    }

    /**
     * {@inheritdoc}
     */
    public function only(array $ids): McpServerCollection
    {
        return $this->servers->only($ids);
    }

    /**
     * {@inheritdoc}
     */
    public function except(array $ids): McpServerCollection
    {
        return $this->servers->except($ids);
    }

    /**
     * {@inheritdoc}
     */
    public function connectAll(): void
    {
        foreach ($this->servers as $server) {
            if (! $server->isConnected()) {
                $server->connect();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function disconnectAll(): void
    {
        foreach ($this->servers as $server) {
            if ($server->isConnected()) {
                $server->disconnect();
            }
        }
    }
}
