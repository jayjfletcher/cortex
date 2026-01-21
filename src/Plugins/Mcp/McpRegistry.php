<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Mcp;

use Illuminate\Support\Collection;
use JayI\Cortex\Exceptions\McpException;
use JayI\Cortex\Plugins\Mcp\Contracts\McpRegistryContract;
use JayI\Cortex\Plugins\Mcp\Contracts\McpServerContract;

class McpRegistry implements McpRegistryContract
{
    /**
     * @var array<string, McpServerContract>
     */
    protected array $servers = [];

    /**
     * {@inheritdoc}
     */
    public function register(McpServerContract $server): void
    {
        $this->servers[$server->id()] = $server;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $id): McpServerContract
    {
        if (! $this->has($id)) {
            throw McpException::serverNotFound($id);
        }

        return $this->servers[$id];
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool
    {
        return isset($this->servers[$id]);
    }

    /**
     * {@inheritdoc}
     */
    public function all(): Collection
    {
        return collect($this->servers);
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
