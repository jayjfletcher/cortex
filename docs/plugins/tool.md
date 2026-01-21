# Tool Plugin

The Tool plugin provides function calling (tool use) support, allowing LLMs to invoke defined functions with validated input and receive structured results.

## Overview

- **Plugin ID:** `tool`
- **Dependencies:** `schema`, `chat`
- **Provides:** `tools`

## Creating Tools

### Fluent Builder

```php
use JayI\Cortex\Plugins\Tool\Tool;
use JayI\Cortex\Plugins\Tool\ToolResult;
use JayI\Cortex\Plugins\Schema\Schema;

$weatherTool = Tool::make('get_weather')
    ->withDescription('Get current weather for a location')
    ->withInput(
        Schema::object()
            ->property('location', Schema::string()->description('City name'))
            ->property('units', Schema::enum(['celsius', 'fahrenheit'])->default('celsius'))
            ->required('location')
    )
    ->withHandler(function (array $input, ToolContext $context) {
        $location = $input['location'];
        $weather = WeatherService::get($location);

        return ToolResult::success([
            'temperature' => $weather->temperature,
            'conditions' => $weather->conditions,
        ]);
    })
    ->withTimeout(30);
```

### From Invokable Class

```php
use JayI\Cortex\Plugins\Tool\Tool;

// Define an invokable class
class GetWeatherTool
{
    public function __invoke(string $location, string $units = 'celsius'): array
    {
        $weather = WeatherService::get($location);

        return [
            'temperature' => $weather->temperature,
            'conditions' => $weather->conditions,
        ];
    }
}

// Create tool from class (schema is inferred from method signature)
$tool = Tool::fromInvokable(GetWeatherTool::class);
```

## Tool Contract

All tools implement `ToolContract`:

```php
interface ToolContract
{
    public function name(): string;
    public function description(): string;
    public function inputSchema(): Schema;
    public function outputSchema(): ?Schema;
    public function execute(array $input, ToolContext $context): ToolResult;
    public function timeout(): ?int;
    public function toDefinition(): array;
}
```

## Tool Results

Tools return `ToolResult` objects to communicate with the agent:

```php
use JayI\Cortex\Plugins\Tool\ToolResult;

// Successful result
$result = ToolResult::success(['data' => 'value']);

// Error result
$result = ToolResult::error('Something went wrong');

// Stop the agent loop
$result = ToolResult::stop('Final answer here');

// Add metadata
$result = ToolResult::success($data)->withMetadata([
    'execution_time' => 0.5,
]);
```

### Result Methods

```php
$result = ToolResult::success(['key' => 'value']);

// Check type
$result->success;        // bool
$result->shouldStop();   // bool - Should agent loop terminate?

// Access data
$result->output;         // mixed - The result data
$result->error;          // ?string - Error message if failed

// Convert for LLM
$result->toContentString(); // JSON string for LLM consumption
```

## Tool Context

The `ToolContext` provides execution context to tool handlers:

```php
use JayI\Cortex\Plugins\Tool\ToolContext;

$context = new ToolContext(
    conversationId: 'conv-123',
    agentId: 'agent-456',
    tenantId: 'tenant-789',
    triggeringMessage: $message,
    metadata: ['custom' => 'data'],
);

// In a handler
$tool = Tool::make('my_tool')
    ->withHandler(function (array $input, ToolContext $context) {
        $convId = $context->conversationId;
        $meta = $context->metadata;
        // ...
    });
```

## Tool Collections

Group tools together:

```php
use JayI\Cortex\Plugins\Tool\ToolCollection;

$tools = ToolCollection::make([
    $weatherTool,
    $calculatorTool,
    $searchTool,
]);

// Find a tool by name
$tool = $tools->find('get_weather');

// Check if tool exists
$tools->has('get_weather'); // true

// Get all tools
$tools->all();

// Count
$tools->count();

// Iterate
foreach ($tools as $tool) {
    echo $tool->name();
}

// Convert to definitions for API
$definitions = $tools->toDefinitions();
```

## Tool Registry

The registry manages tool discovery and access:

```php
use JayI\Cortex\Plugins\Tool\Contracts\ToolRegistryContract;

$registry = app(ToolRegistryContract::class);

// Register a tool
$registry->register($weatherTool);

// Get a tool
$tool = $registry->get('get_weather');

// Check existence
$registry->has('get_weather');

// Get all tools
$tools = $registry->all();

// Get as collection
$collection = $registry->collection();
```

## Tool Executor

Execute tools with proper error handling:

```php
use JayI\Cortex\Plugins\Tool\ToolExecutor;

$executor = app(ToolExecutor::class);

$result = $executor->execute(
    tool: $tool,
    input: ['location' => 'Paris'],
    context: new ToolContext()
);

if ($result->success) {
    $data = $result->output;
} else {
    $error = $result->error;
}
```

## Using Tools with Chat

```php
use JayI\Cortex\Plugins\Chat\ChatRequestBuilder;

$response = (new ChatRequestBuilder())
    ->system('You can check the weather using tools.')
    ->message('What is the weather in Paris?')
    ->tools([$weatherTool])
    ->send();

// Check if tool use is requested
if ($response->requiresToolExecution()) {
    foreach ($response->toolCalls() as $toolCall) {
        $tool = $tools->find($toolCall->name);
        $result = $tool->execute($toolCall->input, $context);

        // Add result to conversation and continue
        // ...
    }
}
```

## Tool Attributes

Use PHP attributes for tool discovery:

### Class Attribute

```php
use JayI\Cortex\Plugins\Tool\Attributes\Tool as ToolAttribute;

#[ToolAttribute(
    name: 'calculate_sum',
    description: 'Add two numbers together'
)]
class CalculateSumTool
{
    public function __invoke(int $a, int $b): int
    {
        return $a + $b;
    }
}
```

### Parameter Attribute

```php
use JayI\Cortex\Plugins\Tool\Attributes\Tool as ToolAttribute;
use JayI\Cortex\Plugins\Tool\Attributes\ToolParameter;

#[ToolAttribute(name: 'search', description: 'Search for items')]
class SearchTool
{
    public function __invoke(
        #[ToolParameter(description: 'Search query', minLength: 1)]
        string $query,

        #[ToolParameter(description: 'Max results to return', minimum: 1, maximum: 100)]
        int $limit = 10
    ): array {
        return Search::query($query)->limit($limit)->get();
    }
}
```

### Auto-Discovery

Register tools from a directory:

```php
// In a service provider
use JayI\Cortex\Plugins\Tool\Contracts\ToolRegistryContract;

public function boot(): void
{
    $registry = app(ToolRegistryContract::class);

    // The registry can discover tools with the #[Tool] attribute
    $registry->discover(app_path('Tools'));
}
```

## Extension Points

Register tools via the plugin extension system:

```php
use JayI\Cortex\Contracts\PluginManagerContract;

public function boot(PluginManagerContract $manager): void
{
    $manager->extend('tools', $myTool);
}
```

## Hooks

The Tool plugin provides execution hooks:

```php
$manager->addHook('tool.before_execute', function ($input, $tool, $context) {
    // Validate or modify input before execution
    return $input;
}, priority: 10);

$manager->addHook('tool.after_execute', function ($result, $tool, $input, $context) {
    // Log or modify result after execution
    return $result;
}, priority: 10);
```

## Error Handling

```php
use JayI\Cortex\Exceptions\ToolException;

try {
    $result = $executor->execute($tool, $input, $context);
} catch (ToolException $e) {
    // Tool not found
    if ($e->getMessage() === 'Tool not found') {
        // Handle missing tool
    }

    // Execution failed
    $context = $e->context();
}
```

Common exceptions:

```php
// Tool not registered
ToolException::notFound($name);

// Execution timed out
ToolException::timeout($name, $seconds);

// Execution failed
ToolException::executionFailed($name, $message, $previous);

// Validation failed
ToolException::validationFailed($name, $errors);
```

## Configuration

```php
// config/cortex.php
'tool' => [
    // Default execution timeout in seconds
    'default_timeout' => 30,

    // Directories to scan for tool discovery
    'discovery_paths' => [
        app_path('Tools'),
    ],
],
```

## Complete Example

```php
use JayI\Cortex\Plugins\Tool\Tool;
use JayI\Cortex\Plugins\Tool\ToolResult;
use JayI\Cortex\Plugins\Tool\ToolContext;
use JayI\Cortex\Plugins\Tool\ToolCollection;
use JayI\Cortex\Plugins\Chat\ChatRequestBuilder;
use JayI\Cortex\Plugins\Chat\Messages\Message;
use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;
use JayI\Cortex\Plugins\Schema\Schema;

// Define tools
$tools = ToolCollection::make([
    Tool::make('get_weather')
        ->withDescription('Get current weather')
        ->withInput(
            Schema::object()
                ->property('location', Schema::string())
                ->required('location')
        )
        ->withHandler(fn ($input) => ToolResult::success([
            'temperature' => 22,
            'conditions' => 'Sunny',
        ])),

    Tool::make('search_web')
        ->withDescription('Search the web')
        ->withInput(
            Schema::object()
                ->property('query', Schema::string())
                ->required('query')
        )
        ->withHandler(fn ($input) => ToolResult::success([
            'results' => ['Result 1', 'Result 2'],
        ])),
]);

// Initial request
$messages = MessageCollection::make()
    ->user('What is the weather in Tokyo?');

$response = (new ChatRequestBuilder())
    ->messages($messages)
    ->tools($tools)
    ->send();

// Handle tool calls
while ($response->requiresToolExecution()) {
    $messages->add($response->message);

    foreach ($response->toolCalls() as $toolCall) {
        $tool = $tools->find($toolCall->name);
        $result = $tool->execute($toolCall->input, new ToolContext());

        $messages->add(Message::toolResult($toolCall->id, $result->output));
    }

    $response = (new ChatRequestBuilder())
        ->messages($messages)
        ->tools($tools)
        ->send();
}

echo $response->content();
```
