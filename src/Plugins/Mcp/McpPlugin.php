<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Mcp;

use Illuminate\Contracts\Container\Container;
use JayI\Cortex\Contracts\PluginContract;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Plugins\Mcp\Contracts\McpRegistryContract;
use JayI\Cortex\Plugins\Mcp\Servers\HttpMcpServer;
use JayI\Cortex\Plugins\Mcp\Servers\StdioMcpServer;
use JayI\Cortex\Support\ExtensionPoint;

class McpPlugin implements PluginContract
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected Container $container,
        protected array $config = [],
    ) {}

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return 'mcp';
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return 'MCP (Model Context Protocol)';
    }

    /**
     * {@inheritdoc}
     */
    public function version(): string
    {
        return '1.0.0';
    }

    /**
     * {@inheritdoc}
     */
    public function dependencies(): array
    {
        return ['tool'];
    }

    /**
     * {@inheritdoc}
     */
    public function provides(): array
    {
        return ['mcp'];
    }

    /**
     * {@inheritdoc}
     */
    public function register(PluginManagerContract $manager): void
    {
        // Register MCP registry
        $this->container->singleton(McpRegistryContract::class, function () {
            return new McpRegistry;
        });

        // Register extension point for MCP servers
        $manager->registerExtensionPoint(
            'mcp_servers',
            ExtensionPoint::make('mcp_servers', Contracts\McpServerContract::class)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function boot(PluginManagerContract $manager): void
    {
        $registry = $this->container->make(McpRegistryContract::class);

        // Register servers from config (if discovery is enabled)
        $discoveryEnabled = $this->config['discovery']['enabled'] ?? true;
        if ($discoveryEnabled) {
            $servers = $this->config['servers'] ?? [];
            foreach ($servers as $id => $serverConfig) {
                $server = $this->createServerFromConfig($id, $serverConfig);
                if ($server !== null) {
                    $registry->register($server);
                }
            }
        }

        // Register servers from extension point
        $extensionPoint = $manager->getExtensionPoint('mcp_servers');
        foreach ($extensionPoint->all() as $server) {
            $registry->register($server);
        }

        // Auto-connect if configured
        if ($this->config['auto_connect'] ?? false) {
            $registry->connectAll();
        }
    }

    /**
     * Create an MCP server from config.
     *
     * @param  array<string, mixed>  $config
     */
    protected function createServerFromConfig(string $id, array $config): ?Contracts\McpServerContract
    {
        $transport = McpTransport::tryFrom($config['transport'] ?? 'stdio');

        return match ($transport) {
            McpTransport::Stdio => StdioMcpServer::fromConfig($id, $config),
            McpTransport::Http => HttpMcpServer::fromConfig($id, $config),
            McpTransport::Sse => null, // TODO: Implement SSE server
            default => null,
        };
    }
}
