# MCP Plugin (Model Context Protocol)

The MCP plugin provides integration with Model Context Protocol servers, enabling extended tool capabilities, resource access, and prompt templates from external sources.

## Overview

- **Plugin ID:** `mcp`
- **Dependencies:** `tool`
- **Provides:** `mcp`

## What is MCP?

The Model Context Protocol (MCP) is an open protocol that enables LLM applications to connect to external data sources and tools. MCP servers can provide:

- **Tools** - Functions that the LLM can call
- **Resources** - Data that can be read and used as context
- **Prompts** - Reusable prompt templates

## Supported Transports

| Transport | Description | Use Case |
|-----------|-------------|----------|
| `stdio` | Standard input/output | Local processes spawned by your application |
| `http` | HTTP/HTTPS transport | Remote MCP servers accessible via HTTP |
| `sse` | Server-Sent Events | Remote servers with streaming (coming soon) |

## Configuration

### Config-Based Registration

```php
// config/cortex.php
'mcp' => [
    // Discovery settings
    'discovery' => [
        'enabled' => true, // Set to false to disable auto-registration of servers from config
    ],

    'auto_connect' => false, // Connect all registered servers on boot

    'servers' => [
        // Local stdio server
        'filesystem' => [
            'name' => 'Filesystem Access',
            'transport' => 'stdio',
            'command' => 'npx',
            'args' => ['-y', '@modelcontextprotocol/server-filesystem', '/path/to/dir'],
        ],

        // Custom local server
        'my-tools' => [
            'name' => 'My Custom Tools',
            'transport' => 'stdio',
            'command' => 'node',
            'args' => ['./mcp-servers/my-tools/index.js'],
            'cwd' => base_path(),
            'env' => [
                'API_KEY' => env('MY_API_KEY'),
            ],
        ],

        // Remote HTTP server
        'remote-api' => [
            'name' => 'Remote API Tools',
            'transport' => 'http',
            'url' => 'https://mcp-server.example.com/rpc',
            'headers' => [
                'Authorization' => 'Bearer ' . env('MCP_API_KEY'),
            ],
            'timeout' => 30,
            'verify_ssl' => true,
        ],
    ],
],
```

### Discovery Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `discovery.enabled` | bool | `true` | When true, servers defined in config are automatically registered on boot |
| `auto_connect` | bool | `false` | When true, all registered servers are connected automatically on boot |

**When to disable discovery:**

- When you want to programmatically register servers at runtime
- When servers should be conditionally registered based on environment or tenant
- When using only the extension point system for server registration

```php
// Disable auto-registration and register servers manually
'mcp' => [
    'discovery' => [
        'enabled' => false,
    ],
    // servers are still defined but won't be auto-registered
    'servers' => [...],
],

// Then register manually in a service provider:
$registry = app(McpRegistryContract::class);
$registry->register(StdioMcpServer::fromConfig('my-server', config('cortex.mcp.servers.my-server')));
```

### HTTP Server Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `name` | string | server id | Display name for the server |
| `transport` | string | `'stdio'` | Must be `'http'` for HTTP transport |
| `url` | string | required | Full URL to the MCP server endpoint |
| `headers` | array | `[]` | Additional HTTP headers (e.g., for authentication) |
| `timeout` | int | `30` | Request timeout in seconds |
| `verify_ssl` | bool | `true` | Whether to verify SSL certificates |

## MCP Registry

Access MCP servers through the registry:

```php
use JayI\Cortex\Plugins\Mcp\Contracts\McpRegistryContract;
use JayI\Cortex\Plugins\Mcp\McpServerCollection;

$registry = app(McpRegistryContract::class);

// Get a server
$server = $registry->get('filesystem');

// Check existence
$registry->has('filesystem'); // true

// List all servers (returns McpServerCollection)
$servers = $registry->all();

// Get specific servers (returns McpServerCollection)
$subset = $registry->only(['filesystem', 'database']);

// Get all except specified (returns McpServerCollection)
$filtered = $registry->except(['deprecated-server']);

// Connect all servers
$registry->connectAll();

// Disconnect all
$registry->disconnectAll();
```

## MCP Server Contract

All MCP servers implement `McpServerContract`:

```php
interface McpServerContract
{
    public function id(): string;
    public function name(): string;
    public function transport(): McpTransport;
    public function connect(): void;
    public function disconnect(): void;
    public function isConnected(): bool;
    public function tools(): ToolCollection;
    public function resources(): array;
    public function readResource(string $uri): McpResourceContent;
    public function prompts(): array;
    public function getPrompt(string $name, array $arguments = []): McpPromptResult;
}
```

## Using MCP Tools

MCP servers expose tools that integrate with Cortex:

```php
$server = $registry->get('filesystem');
$server->connect();

// Get available tools
$tools = $server->tools();

foreach ($tools as $tool) {
    echo "{$tool->name()}: {$tool->description()}\n";
}

// Use tools in an agent
$agent = Agent::make('file-assistant')
    ->withTools($tools)
    ->withSystemPrompt('You can read and manage files.');

$response = $agent->run('List files in the current directory');
```

### Using Tools in Chat

```php
use JayI\Cortex\Plugins\Chat\ChatRequestBuilder;

$server = $registry->get('my-tools');
$server->connect();

$response = (new ChatRequestBuilder())
    ->message('Use the available tools to help me')
    ->withTools($server->tools())
    ->send();
```

## MCP Resources

Resources provide read-only access to data:

```php
$server = $registry->get('filesystem');

// List available resources
$resources = $server->resources();

foreach ($resources as $resource) {
    echo "URI: {$resource->uri}\n";
    echo "Name: {$resource->name}\n";
    echo "Type: {$resource->mimeType}\n";
}

// Read a resource
$content = $server->readResource('file:///path/to/file.txt');

echo $content->content;  // File contents
echo $content->mimeType; // text/plain

// Check content type
if ($content->isText()) {
    echo $content->content;
}

if ($content->mimeType === 'application/json') {
    $data = $content->json();
}
```

### McpResource

```php
use JayI\Cortex\Plugins\Mcp\McpResource;

class McpResource
{
    public string $uri;          // Resource URI
    public string $name;         // Display name
    public ?string $description; // Optional description
    public ?string $mimeType;    // MIME type
    public array $metadata;      // Additional metadata
}
```

### McpResourceContent

```php
use JayI\Cortex\Plugins\Mcp\McpResourceContent;

class McpResourceContent
{
    public string $uri;
    public string $content;
    public ?string $mimeType;

    public function isText(): bool;
    public function isBinary(): bool;
    public function json(): ?array;
}
```

## MCP Prompts

Prompts are reusable templates:

```php
$server = $registry->get('prompts-server');

// List available prompts
$prompts = $server->prompts();

foreach ($prompts as $prompt) {
    echo "Name: {$prompt->name}\n";
    echo "Description: {$prompt->description}\n";

    foreach ($prompt->arguments as $arg) {
        echo "  - {$arg->name}: {$arg->description}";
        echo $arg->required ? ' (required)' : '';
        echo "\n";
    }
}

// Get a prompt with arguments
$result = $server->getPrompt('code-review', [
    'language' => 'php',
    'style' => 'concise',
]);

echo $result->description;
echo $result->text(); // Combined message content

// Use in chat
$messages = $result->toMessageCollection();
```

### McpPrompt

```php
use JayI\Cortex\Plugins\Mcp\McpPrompt;
use JayI\Cortex\Plugins\Mcp\McpPromptArgument;

class McpPrompt
{
    public string $name;
    public ?string $description;
    public array $arguments; // McpPromptArgument[]
}

class McpPromptArgument
{
    public string $name;
    public ?string $description;
    public bool $required;
}
```

### McpPromptResult

```php
use JayI\Cortex\Plugins\Mcp\McpPromptResult;

class McpPromptResult
{
    public ?string $description;
    public array $messages; // McpPromptMessage[]

    public function toMessageCollection(): MessageCollection;
    public function text(): string;
}
```

## Creating MCP Servers

### Stdio Server

```php
use JayI\Cortex\Plugins\Mcp\Servers\StdioMcpServer;

$server = new StdioMcpServer(
    serverId: 'my-server',
    serverName: 'My MCP Server',
    command: 'node',
    args: ['./server.js'],
    cwd: base_path(),
    env: ['API_KEY' => 'secret'],
);

// Or from config
$server = StdioMcpServer::fromConfig('my-server', [
    'name' => 'My Server',
    'command' => 'node',
    'args' => ['./server.js'],
]);

// Register
$registry->register($server);
```

### HTTP Server

```php
use JayI\Cortex\Plugins\Mcp\Servers\HttpMcpServer;

$server = new HttpMcpServer(
    serverId: 'remote-api',
    serverName: 'Remote API Server',
    url: 'https://mcp-server.example.com/rpc',
    headers: ['Authorization' => 'Bearer secret-token'],
    timeout: 30,
    verifySsl: true,
);

// Or from config
$server = HttpMcpServer::fromConfig('remote-api', [
    'name' => 'Remote API Server',
    'url' => 'https://mcp-server.example.com/rpc',
    'headers' => ['Authorization' => 'Bearer secret-token'],
    'timeout' => 30,
    'verify_ssl' => true,
]);

// Register
$registry->register($server);
```

The HTTP server uses Laravel's HTTP client and implements JSON-RPC 2.0 over HTTP POST requests. It supports:

- Custom headers for authentication (API keys, Bearer tokens, etc.)
- Configurable request timeouts
- SSL certificate verification (can be disabled for development)
- Automatic connection management and capability caching

### Extension Point

Register servers via the plugin extension system:

```php
public function boot(PluginManagerContract $manager): void
{
    $manager->extend('mcp_servers', new MyMcpServer());
}
```

## Error Handling

```php
use JayI\Cortex\Exceptions\McpException;

try {
    $server = $registry->get('my-server');
    $server->connect();
    $tools = $server->tools();
} catch (McpException $e) {
    $context = $e->context();
    // Handle error
}
```

Exception types:

```php
// Server not registered
McpException::serverNotFound($id);

// Connection failed
McpException::connectionFailed($id, $message);

// Server not connected
McpException::notConnected($id);

// Tool not found
McpException::toolNotFound($serverId, $toolName);

// Resource not found
McpException::resourceNotFound($serverId, $uri);

// Prompt not found
McpException::promptNotFound($serverId, $promptName);

// Protocol error
McpException::protocolError($serverId, $message);
```

## Complete Example

```php
use JayI\Cortex\Plugins\Agent\Agent;
use JayI\Cortex\Plugins\Mcp\Contracts\McpRegistryContract;
use JayI\Cortex\Plugins\Tool\ToolCollection;

// Get MCP registry
$mcpRegistry = app(McpRegistryContract::class);

// Connect to filesystem server
$filesystem = $mcpRegistry->get('filesystem');
$filesystem->connect();

// Get tools from MCP server
$mcpTools = $filesystem->tools();

// Create an agent with MCP tools
$agent = Agent::make('file-assistant')
    ->withName('File Assistant')
    ->withSystemPrompt(<<<PROMPT
You are a file management assistant. You can:
- List files in directories
- Read file contents
- Search for files
- Provide information about files

Always confirm before any destructive operations.
PROMPT)
    ->withTools($mcpTools)
    ->withMaxIterations(10);

// Run the agent
$response = $agent->run('What files are in the project root?');

echo $response->content;

// List resources
$resources = $filesystem->resources();
foreach ($resources as $resource) {
    echo "{$resource->name}: {$resource->uri}\n";
}

// Read a specific resource
$readme = $filesystem->readResource('file:///project/README.md');
echo $readme->content;

// Disconnect when done
$filesystem->disconnect();
```

## Testing

For testing, you can create mock MCP servers:

```php
use JayI\Cortex\Plugins\Mcp\Contracts\McpServerContract;
use JayI\Cortex\Plugins\Tool\ToolCollection;

class FakeMcpServer implements McpServerContract
{
    protected bool $connected = false;
    protected ToolCollection $tools;

    public function __construct()
    {
        $this->tools = ToolCollection::make([]);
    }

    public function id(): string { return 'fake'; }
    public function name(): string { return 'Fake Server'; }
    public function transport(): McpTransport { return McpTransport::Stdio; }
    public function connect(): void { $this->connected = true; }
    public function disconnect(): void { $this->connected = false; }
    public function isConnected(): bool { return $this->connected; }
    public function tools(): ToolCollection { return $this->tools; }
    // ... implement other methods
}
```
