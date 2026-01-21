<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Mcp\Servers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use JayI\Cortex\Exceptions\McpException;
use JayI\Cortex\Plugins\Mcp\Contracts\McpServerContract;
use JayI\Cortex\Plugins\Mcp\McpPrompt;
use JayI\Cortex\Plugins\Mcp\McpPromptResult;
use JayI\Cortex\Plugins\Mcp\McpResource;
use JayI\Cortex\Plugins\Mcp\McpResourceContent;
use JayI\Cortex\Plugins\Mcp\McpTransport;
use JayI\Cortex\Plugins\Tool\Tool;
use JayI\Cortex\Plugins\Tool\ToolCollection;
use JayI\Cortex\Plugins\Tool\ToolResult;

/**
 * MCP server using HTTP/HTTPS transport.
 *
 * Implements the Model Context Protocol over HTTP using JSON-RPC 2.0.
 * Supports authentication via headers and configurable timeouts.
 *
 * @example Configuration in config/cortex.php:
 * ```php
 * 'mcp' => [
 *     'servers' => [
 *         'remote-api' => [
 *             'transport' => 'http',
 *             'url' => 'https://mcp-server.example.com/rpc',
 *             'headers' => ['Authorization' => 'Bearer token'],
 *             'timeout' => 30,
 *             'verify_ssl' => true,
 *         ],
 *     ],
 * ],
 * ```
 *
 * @see https://spec.modelcontextprotocol.io/ MCP Specification
 */
class HttpMcpServer implements McpServerContract
{
    protected bool $connected = false;

    protected int $requestId = 0;

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $cachedTools = [];

    /**
     * @var array<int, McpResource>
     */
    protected array $cachedResources = [];

    /**
     * @var array<int, McpPrompt>
     */
    protected array $cachedPrompts = [];

    /**
     * @param  array<string, string>  $headers  Additional headers for requests
     */
    public function __construct(
        protected string $serverId,
        protected string $serverName,
        protected string $url,
        protected array $headers = [],
        protected int $timeout = 30,
        protected bool $verifySsl = true,
    ) {}

    /**
     * Create from config array.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $id, array $config): static
    {
        return new static(
            serverId: $id,
            serverName: $config['name'] ?? $id,
            url: $config['url'],
            headers: $config['headers'] ?? [],
            timeout: $config['timeout'] ?? 30,
            verifySsl: $config['verify_ssl'] ?? true,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function id(): string
    {
        return $this->serverId;
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return $this->serverName;
    }

    /**
     * {@inheritdoc}
     */
    public function transport(): McpTransport
    {
        return McpTransport::Http;
    }

    /**
     * {@inheritdoc}
     */
    public function connect(): void
    {
        if ($this->connected) {
            return;
        }

        try {
            // Send initialize request
            $this->sendRequest('initialize', [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [
                    'roots' => ['listChanged' => true],
                ],
                'clientInfo' => [
                    'name' => 'cortex',
                    'version' => '1.0.0',
                ],
            ]);

            // Send initialized notification
            $this->sendNotification('notifications/initialized', []);

            $this->connected = true;

            // Cache capabilities
            $this->refreshCapabilities();
        } catch (\Throwable $e) {
            $this->disconnect();
            throw McpException::connectionFailed($this->serverId, $e->getMessage(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect(): void
    {
        $this->connected = false;
        $this->cachedTools = [];
        $this->cachedResources = [];
        $this->cachedPrompts = [];
    }

    /**
     * {@inheritdoc}
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * {@inheritdoc}
     */
    public function tools(): ToolCollection
    {
        $this->ensureConnected();

        $tools = [];
        foreach ($this->cachedTools as $toolDef) {
            $tools[] = $this->createToolFromDefinition($toolDef);
        }

        return ToolCollection::make($tools);
    }

    /**
     * {@inheritdoc}
     */
    public function resources(): array
    {
        $this->ensureConnected();

        return $this->cachedResources;
    }

    /**
     * {@inheritdoc}
     */
    public function readResource(string $uri): McpResourceContent
    {
        $this->ensureConnected();

        $response = $this->sendRequest('resources/read', ['uri' => $uri]);

        if (! isset($response['contents'][0])) {
            throw McpException::resourceNotFound($this->serverId, $uri);
        }

        $content = $response['contents'][0];

        return new McpResourceContent(
            uri: $content['uri'],
            content: $content['text'] ?? $content['blob'] ?? '',
            mimeType: $content['mimeType'] ?? null,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function prompts(): array
    {
        $this->ensureConnected();

        return $this->cachedPrompts;
    }

    /**
     * {@inheritdoc}
     */
    public function getPrompt(string $name, array $arguments = []): McpPromptResult
    {
        $this->ensureConnected();

        $response = $this->sendRequest('prompts/get', [
            'name' => $name,
            'arguments' => $arguments,
        ]);

        return McpPromptResult::from($response);
    }

    /**
     * Refresh cached capabilities from server.
     */
    protected function refreshCapabilities(): void
    {
        // List tools
        try {
            $response = $this->sendRequest('tools/list', []);
            $this->cachedTools = $response['tools'] ?? [];
        } catch (\Throwable) {
            $this->cachedTools = [];
        }

        // List resources
        try {
            $response = $this->sendRequest('resources/list', []);
            $this->cachedResources = array_map(
                fn ($r) => McpResource::from($r),
                $response['resources'] ?? []
            );
        } catch (\Throwable) {
            $this->cachedResources = [];
        }

        // List prompts
        try {
            $response = $this->sendRequest('prompts/list', []);
            $this->cachedPrompts = array_map(
                fn ($p) => McpPrompt::from($p),
                $response['prompts'] ?? []
            );
        } catch (\Throwable) {
            $this->cachedPrompts = [];
        }
    }

    /**
     * Create a Tool from MCP tool definition.
     *
     * @param  array<string, mixed>  $definition
     */
    protected function createToolFromDefinition(array $definition): Tool
    {
        return Tool::make($definition['name'])
            ->withDescription($definition['description'] ?? '')
            ->withHandler(function (array $input) use ($definition) {
                $response = $this->sendRequest('tools/call', [
                    'name' => $definition['name'],
                    'arguments' => $input,
                ]);

                if (isset($response['isError']) && $response['isError']) {
                    return ToolResult::error($response['content'][0]['text'] ?? 'Tool execution failed');
                }

                $content = $response['content'] ?? [];
                $text = '';
                foreach ($content as $item) {
                    if (isset($item['text'])) {
                        $text .= $item['text'];
                    }
                }

                return ToolResult::success($text ?: $content);
            });
    }

    /**
     * Ensure the server is connected.
     */
    protected function ensureConnected(): void
    {
        if (! $this->isConnected()) {
            throw McpException::notConnected($this->serverId);
        }
    }

    /**
     * Create an HTTP client instance.
     */
    protected function createClient(): PendingRequest
    {
        $client = Http::timeout($this->timeout)
            ->withHeaders(array_merge([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ], $this->headers));

        if (! $this->verifySsl) {
            $client->withoutVerifying();
        }

        return $client;
    }

    /**
     * Send a JSON-RPC request and wait for response.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function sendRequest(string $method, array $params): array
    {
        $this->requestId++;

        $request = [
            'jsonrpc' => '2.0',
            'id' => $this->requestId,
            'method' => $method,
            'params' => $params,
        ];

        $response = $this->createClient()->post($this->url, $request);

        if (! $response->successful()) {
            throw McpException::protocolError(
                $this->serverId,
                "HTTP error: {$response->status()} {$response->reason()}"
            );
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw McpException::protocolError($this->serverId, 'Invalid JSON response');
        }

        if (isset($data['error'])) {
            throw McpException::protocolError(
                $this->serverId,
                $data['error']['message'] ?? 'Unknown error'
            );
        }

        return $data['result'] ?? [];
    }

    /**
     * Send a JSON-RPC notification (no response expected).
     *
     * @param  array<string, mixed>  $params
     */
    protected function sendNotification(string $method, array $params): void
    {
        $notification = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
        ];

        $response = $this->createClient()->post($this->url, $notification);

        // Notifications may return empty response or acknowledgment
        // We don't throw on non-2xx as some servers may not respond to notifications
        if ($response->serverError()) {
            throw McpException::protocolError(
                $this->serverId,
                "HTTP error: {$response->status()} {$response->reason()}"
            );
        }
    }
}
