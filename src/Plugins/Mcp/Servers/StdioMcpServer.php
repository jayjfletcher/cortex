<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Mcp\Servers;

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
use Symfony\Component\Process\Process;

/**
 * MCP server using stdio transport.
 */
class StdioMcpServer implements McpServerContract
{
    protected ?Process $process = null;

    protected bool $connected = false;

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
     * @param  array<int, string>  $args
     * @param  array<string, string>  $env
     */
    public function __construct(
        protected string $serverId,
        protected string $serverName,
        protected string $command,
        protected array $args = [],
        protected ?string $cwd = null,
        protected array $env = [],
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
            command: $config['command'],
            args: $config['args'] ?? [],
            cwd: $config['cwd'] ?? null,
            env: $config['env'] ?? [],
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
        return McpTransport::Stdio;
    }

    /**
     * {@inheritdoc}
     */
    public function connect(): void
    {
        if ($this->connected) {
            return;
        }

        $command = array_merge([$this->command], $this->args);

        $this->process = new Process(
            command: $command,
            cwd: $this->cwd,
            env: array_merge($_ENV, $this->env),
        );

        $this->process->setTimeout(null);

        try {
            $this->process->start();

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
        if ($this->process !== null && $this->process->isRunning()) {
            $this->process->stop();
        }

        $this->process = null;
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
        return $this->connected && $this->process !== null && $this->process->isRunning();
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
        $serverId = $this->serverId;

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
     * Send a JSON-RPC request and wait for response.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function sendRequest(string $method, array $params): array
    {
        static $requestId = 0;
        $requestId++;

        $request = [
            'jsonrpc' => '2.0',
            'id' => $requestId,
            'method' => $method,
            'params' => $params,
        ];

        $this->process->getInput()->write(json_encode($request)."\n");

        // Read response (simplified - real implementation needs proper async handling)
        $output = '';
        $timeout = 30;
        $start = time();

        while (time() - $start < $timeout) {
            $output .= $this->process->getIncrementalOutput();

            if (str_contains($output, "\n")) {
                $lines = explode("\n", $output);
                foreach ($lines as $line) {
                    if (empty(trim($line))) {
                        continue;
                    }

                    $response = json_decode($line, true);
                    if ($response && isset($response['id']) && $response['id'] === $requestId) {
                        if (isset($response['error'])) {
                            throw McpException::protocolError(
                                $this->serverId,
                                $response['error']['message'] ?? 'Unknown error'
                            );
                        }

                        return $response['result'] ?? [];
                    }
                }
            }

            usleep(10000); // 10ms
        }

        throw McpException::protocolError($this->serverId, 'Request timeout');
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

        $this->process->getInput()->write(json_encode($notification)."\n");
    }
}
