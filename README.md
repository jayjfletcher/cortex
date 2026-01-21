# Cortex - Laravel LLM Package

Cortex is a Laravel 12 package for interacting with Large Language Models (LLMs). It features a robust plugin system for extensibility and provides a clean, fluent API for AI-powered applications.

## Features

- **Plugin-Based Architecture** - Modular design with core and optional plugins
- **Multiple Providers** - AWS Bedrock support with more coming
- **Streaming Support** - Real-time streaming with Laravel Echo and SSE broadcasting
- **Tool/Function Calling** - Define and execute tools with automatic schema generation
- **Structured Output** - Enforce typed responses from LLMs with validation
- **Schema Validation** - JSON Schema generation and validation with casting
- **Autonomous Agents** - Build agents with memory, tools, and agentic loops
- **MCP Integration** - Connect to Model Context Protocol servers for extended capabilities
- **Workflow Orchestration** - Build complex multi-step processes with branching, loops, and human-in-the-loop
- **Testing Support** - FakeProvider for testing without API calls

## Requirements

- PHP 8.2+
- Laravel 12.x
- AWS SDK (for Bedrock provider)

## Installation

```bash
composer require jayi/cortex
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=cortex-config
```

## Quick Start

### Basic Chat

```php
use JayI\Cortex\Facades\Cortex;

// Simple message
$response = Cortex::chat()
    ->message('What is the capital of France?')
    ->send();

echo $response->content(); // "The capital of France is Paris."
```

### Streaming

```php
$stream = Cortex::chat()
    ->message('Write a short story about a robot.')
    ->stream();

foreach ($stream->text() as $chunk) {
    echo $chunk;
}
```

### With Tools

```php
use JayI\Cortex\Plugins\Tool\Tool;
use JayI\Cortex\Plugins\Schema\Schema;

$weatherTool = Tool::make('get_weather')
    ->withDescription('Get current weather for a location')
    ->withInput(
        Schema::object()
            ->property('location', Schema::string()->description('City name'))
            ->required('location')
    )
    ->withHandler(function (array $input) {
        return ToolResult::success(['temperature' => 22, 'conditions' => 'Sunny']);
    });

$response = Cortex::chat()
    ->message('What is the weather in Paris?')
    ->withTools([$weatherTool])
    ->send();
```

### Structured Output

```php
use JayI\Cortex\Plugins\Schema\Schema;

$schema = Schema::object()
    ->property('sentiment', Schema::enum(['positive', 'negative', 'neutral']))
    ->property('confidence', Schema::number()->minimum(0)->maximum(1))
    ->required('sentiment', 'confidence');

$response = Cortex::structuredOutput()->generate(
    Cortex::chat()->message('Analyze: "This product is amazing!"')->build(),
    $schema
);

// ['sentiment' => 'positive', 'confidence' => 0.95]
```

### Autonomous Agents

```php
use JayI\Cortex\Plugins\Agent\Agent;
use JayI\Cortex\Plugins\Agent\Memory\BufferMemory;

$agent = Agent::make('research-assistant')
    ->withSystemPrompt('You are a research assistant with access to web search.')
    ->withTools([$searchTool, $calculatorTool])
    ->withMemory(new BufferMemory())
    ->withMaxIterations(10);

$response = $agent->run('What is the population of Tokyo?');

echo $response->content;
echo "Completed in {$response->iterationCount} iterations";
```

## Configuration

See `config/cortex.php` for all configuration options:

```php
return [
    'plugins' => [
        'enabled' => [
            'tool',
            'structured-output',
        ],
    ],

    'provider' => [
        'default' => 'bedrock',
        'providers' => [
            'bedrock' => [
                'region' => env('AWS_REGION', 'us-east-1'),
                'default_model' => env('CORTEX_DEFAULT_MODEL', 'anthropic.claude-3-5-sonnet-20241022-v2:0'),
            ],
        ],
    ],
];
```

## Documentation

- [Plugin System](docs/plugin-system.md)
- [Schema Plugin](docs/plugins/schema.md)
- [Provider Plugin](docs/plugins/provider.md)
- [Chat Plugin](docs/plugins/chat.md)
- [Tool Plugin](docs/plugins/tool.md)
- [Structured Output Plugin](docs/plugins/structured-output.md)
- [Agent Plugin](docs/plugins/agent.md)
- [MCP Plugin](docs/plugins/mcp.md)
- [Workflow Plugin](docs/plugins/workflow.md)
- [Resilience Plugin](docs/plugins/resilience.md)
- [Usage Plugin](docs/plugins/usage.md)
- [Guardrail Plugin](docs/plugins/guardrail.md)
- [Cache Plugin](docs/plugins/cache.md)
- [Context Manager Plugin](docs/plugins/context-manager.md)

## Architecture

Cortex uses a plugin-based architecture where functionality is organized into discrete plugins:

**Core Plugins (Always Loaded):**
- **Schema** - JSON Schema generation and validation
- **Provider** - LLM provider management and abstraction
- **Chat** - Chat completion and streaming

**Optional Plugins:**
- **Tool** - Tool/function calling support
- **Structured Output** - Enforce typed responses
- **Agent** - Autonomous agents with memory and tool use
- **MCP** - Model Context Protocol integration
- **Workflow** - Multi-step workflow orchestration
- **Resilience** - Retry, circuit breaker, rate limiting, fallbacks
- **Usage** - Token tracking, cost estimation, budget management
- **Guardrail** - Content filtering, PII detection, prompt injection protection
- **Cache** - Response caching with exact and semantic matching
- **Context Manager** - Context window management and message pruning

## Testing

Cortex provides a `FakeProvider` for testing:

```php
use JayI\Cortex\Plugins\Provider\Providers\FakeProvider;

$fake = FakeProvider::text('Mocked response');

$fake->assertSentCount(0);

$response = $fake->chat($request);

$fake->assertSentCount(1);
$fake->assertSent(fn ($r) => str_contains($r->messages->last()->text(), 'expected'));
```

## License

MIT License. See [LICENSE](LICENSE) for details.
