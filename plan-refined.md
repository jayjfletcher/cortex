# Cortex - Laravel LLM Package

Cortex is a Laravel 12 package for interacting with LLMs. It features a robust plugin system for extensibility and serves as the foundation for a future application with interfaces for creating, testing, and versioning Agents, Workflows, and their prompts.

---

## Technology Stack

- **Framework:** Laravel 12
- **DTOs/ValueObjects:** spatie/laravel-data
- **Testing:** Pest PHP
- **Code Style:** Laravel Pint
- **Architecture:** Plugin-based for extensibility

---

## Design Principles

- Use `declare(strict_types=1)` in all PHP files
- Use interfaces and container injection throughout
- Maintain fluent interfaces with method chaining where appropriate
- Use factory methods for exceptions
- Follow DRY principles; leverage Laravel/Illuminate components
- Test everything with Pest
- Document everything; each plugin gets its own documentation file
- Write clean, production-quality code with proper error handling

---

## Plugin System Architecture

### Overview

The plugin system is the foundation of Cortex's extensibility. Plugins are self-contained units that can:
- Register new functionality
- Extend existing functionality via hooks and extension points
- Replace core implementations
- Depend on other plugins

### Plugin Contract

```php
interface PluginContract
{
    /**
     * Unique identifier for this plugin.
     */
    public function id(): string;

    /**
     * Human-readable name.
     */
    public function name(): string;

    /**
     * Plugin version.
     */
    public function version(): string;

    /**
     * List of plugin IDs this plugin depends on.
     * @return array<string>
     */
    public function dependencies(): array;

    /**
     * List of features/capabilities this plugin provides.
     * @return array<string>
     */
    public function provides(): array;

    /**
     * Register bindings, configs, and extension points.
     * Called before boot, during container setup.
     */
    public function register(PluginManager $manager): void;

    /**
     * Bootstrap the plugin after all plugins are registered.
     * Safe to resolve dependencies here.
     */
    public function boot(PluginManager $manager): void;
}
```

### Plugin Manager

```php
interface PluginManagerContract
{
    /**
     * Register a plugin.
     */
    public function register(PluginContract $plugin): void;

    /**
     * Boot all registered plugins in dependency order.
     */
    public function boot(): void;

    /**
     * Check if a feature is provided by any plugin.
     */
    public function hasFeature(string $feature): bool;

    /**
     * Get the plugin providing a feature.
     */
    public function getFeatureProvider(string $feature): ?PluginContract;

    /**
     * Register an extension point.
     */
    public function registerExtensionPoint(string $name, ExtensionPointContract $point): void;

    /**
     * Extend an extension point.
     */
    public function extend(string $extensionPoint, mixed $extension): void;

    /**
     * Register a hook (filter/modify data).
     */
    public function addHook(string $name, callable $callback, int $priority = 10): void;

    /**
     * Apply hooks to filter data.
     */
    public function applyHooks(string $name, mixed $value, mixed ...$args): mixed;

    /**
     * Replace a bound implementation.
     */
    public function replace(string $abstract, string $concrete): void;
}
```

### Extension Points

Extension points are explicit places where plugins can inject functionality:

```php
interface ExtensionPointContract
{
    public function name(): string;
    public function accepts(): string; // Interface/class that extensions must implement
    public function register(mixed $extension): void;
    public function all(): Collection;
}
```

**Standard Extension Points:**
- `providers` - Register new LLM providers
- `tools` - Register tools
- `agents` - Register agents
- `workflows` - Register workflows
- `guardrails` - Register guardrails
- `message_transformers` - Transform messages before/after sending
- `response_processors` - Process responses before returning

### Hooks

Hooks allow data modification at specific points:

```php
// Registering a hook
$manager->addHook('chat.before_send', function (ChatRequest $request) {
    // Modify request
    return $request;
}, priority: 10);

// Applying hooks
$request = $manager->applyHooks('chat.before_send', $request);
```

**Standard Hooks:**
- `chat.before_send` - Modify ChatRequest before sending
- `chat.after_receive` - Modify ChatResponse after receiving
- `tool.before_execute` - Modify tool input before execution
- `tool.after_execute` - Modify tool output after execution
- `agent.before_iteration` - Before each agent loop iteration
- `agent.after_iteration` - After each agent loop iteration
- `workflow.before_node` - Before node execution
- `workflow.after_node` - After node execution

### Plugin Configuration

```php
// config/cortex.php
return [
    'plugins' => [
        'enabled' => [
            'provider',
            'schema',
            'chat',
            'tool',
            'structured-output',
            'agent',
            'workflow',
            // ...
        ],
        'disabled' => [
            // Explicitly disabled plugins
        ],
    ],
    
    // Plugin-specific configuration sections
    'provider' => [...],
    'chat' => [...],
    // ...
];
```

---

## Multi-Tenancy Support

Cortex supports multiple API keys and providers per tenant through a tenant context system:

```php
interface TenantContextContract
{
    public function id(): string|int|null;
    public function getProviderConfig(string $provider): array;
    public function getApiKey(string $provider): ?string;
    public function getSettings(): array;
}

interface TenantResolverContract
{
    public function resolve(): ?TenantContextContract;
}
```

### Tenant-Aware Components

All registries and managers accept an optional tenant context:

```php
// Global usage (default tenant)
$provider = Cortex::provider('bedrock');

// Tenant-specific usage
$provider = Cortex::forTenant($tenant)->provider('bedrock');

// Or via context
Cortex::withTenant($tenant, function () {
    // All operations use this tenant's config
    $response = Cortex::chat()->send($request);
});
```

### Configuration Resolution

```php
// Tenant configs override global configs
// Priority: Tenant Config > Environment > Default Config

'providers' => [
    'bedrock' => [
        'region' => env('AWS_REGION', 'us-east-1'),
        // Tenant can override via TenantContext::getProviderConfig()
    ],
],
```

---

## Event System

### Event Categories

All events extend a base `CortexEvent` class and are dispatchable via Laravel's event system:

```php
abstract class CortexEvent
{
    public readonly float $timestamp;
    public readonly ?string $tenantId;
    public readonly ?string $correlationId;
    public readonly array $metadata;
}
```

### Provider Events

| Event | Description | Payload |
|-------|-------------|---------|
| `ProviderRegistered` | Provider added to registry | provider, capabilities |
| `BeforeProviderRequest` | Before API call | provider, request, model |
| `AfterProviderResponse` | After API response | provider, request, response, duration |
| `ProviderError` | API error occurred | provider, request, exception |
| `ProviderRateLimited` | Rate limit hit | provider, retryAfter |

### Chat Events

| Event | Description | Payload |
|-------|-------------|---------|
| `BeforeChatSend` | Before sending chat request | request |
| `AfterChatReceive` | After receiving response | request, response |
| `ChatStreamStarted` | Stream began | request |
| `ChatStreamChunk` | Stream chunk received | chunk, index |
| `ChatStreamCompleted` | Stream finished | request, fullResponse |
| `ChatBroadcasting` | Broadcasting to channel | request, channel |
| `ChatError` | Chat error occurred | request, exception |

### Tool Events

| Event | Description | Payload |
|-------|-------------|---------|
| `ToolRegistered` | Tool added to registry | tool |
| `BeforeToolExecution` | Before tool runs | tool, input, context |
| `AfterToolExecution` | After tool completes | tool, input, output, duration |
| `ToolError` | Tool execution failed | tool, input, exception |

### Agent Events

| Event | Description | Payload |
|-------|-------------|---------|
| `AgentRegistered` | Agent added to registry | agent |
| `AgentRunStarted` | Agent run began | agent, input |
| `AgentIterationStarted` | Loop iteration began | agent, iteration, state |
| `AgentIterationCompleted` | Loop iteration ended | agent, iteration, state, response |
| `AgentToolCalled` | Agent invoked a tool | agent, tool, input |
| `AgentRunCompleted` | Agent run finished | agent, input, output, iterations |
| `AgentRunFailed` | Agent run failed | agent, input, exception, iterations |
| `AgentMaxIterationsReached` | Hit iteration limit | agent, state |

### Workflow Events

| Event | Description | Payload |
|-------|-------------|---------|
| `WorkflowRegistered` | Workflow added to registry | workflow |
| `WorkflowStarted` | Workflow execution began | workflow, input |
| `WorkflowNodeEntered` | Entered a node | workflow, node, state |
| `WorkflowNodeExited` | Exited a node | workflow, node, state, output |
| `WorkflowBranchTaken` | Conditional branch selected | workflow, node, branch |
| `WorkflowPaused` | Workflow paused (human input) | workflow, state, reason |
| `WorkflowResumed` | Workflow resumed | workflow, state, input |
| `WorkflowCompleted` | Workflow finished | workflow, input, output |
| `WorkflowFailed` | Workflow failed | workflow, state, exception |

### Guardrail Events

| Event | Description | Payload |
|-------|-------------|---------|
| `GuardrailRegistered` | Guardrail added to registry | guardrail |
| `GuardrailChecked` | Guardrail evaluated | guardrail, content, result |
| `GuardrailBlocked` | Content blocked | guardrail, content, violations |

### Event Configuration

```php
// config/cortex.php
'events' => [
    'enabled' => true,
    
    // Disable specific events for performance
    'disabled' => [
        ChatStreamChunk::class, // High frequency, disable if not needed
    ],
    
    // Automatically log events
    'logging' => [
        'enabled' => true,
        'channel' => 'cortex',
        'level' => 'debug',
        'events' => [
            // Only log these events (empty = all)
            ProviderError::class,
            ChatError::class,
            ToolError::class,
        ],
    ],
    
    // OpenTelemetry integration
    'opentelemetry' => [
        'enabled' => env('CORTEX_OTEL_ENABLED', false),
        'service_name' => 'cortex',
        'traces' => true,
        'metrics' => true,
    ],
],
```

---

## Core Plugins

### Provider Plugin

**Purpose:** LLM provider registry, management, and abstraction layer.

#### Provider Abstraction Strategy

Cortex uses a **balanced abstraction** approach:
- **Abstract common functionality:** chat completion, streaming, token counting, tool use, structured output
- **Expose capabilities:** providers declare what they support via a capabilities object
- **Allow pass-through:** provider-specific options can be passed without abstraction
- **Graceful degradation:** features unsupported by a provider throw clear exceptions or are simulated where possible

```php
interface ProviderContract
{
    public function id(): string;
    public function name(): string;
    
    /**
     * Get provider capabilities.
     */
    public function capabilities(): ProviderCapabilities;
    
    /**
     * List available models.
     */
    public function models(): Collection;
    
    /**
     * Get a specific model.
     */
    public function model(string $id): Model;
    
    /**
     * Count tokens for content.
     */
    public function countTokens(string|array|Message $content, ?string $model = null): int;
    
    /**
     * Send a chat completion request.
     */
    public function chat(ChatRequest $request): ChatResponse;
    
    /**
     * Stream a chat completion request.
     */
    public function stream(ChatRequest $request): StreamedResponse;
    
    /**
     * Check if provider supports a specific feature.
     */
    public function supports(string $feature): bool;
    
    /**
     * Pass provider-specific options.
     * These are merged with request options and passed through to the API.
     */
    public function withOptions(array $options): static;
}
```

#### Provider Capabilities

```php
class ProviderCapabilities extends Data
{
    public function __construct(
        public bool $streaming = false,
        public bool $tools = false,
        public bool $parallelTools = false,
        public bool $vision = false,
        public bool $audio = false,
        public bool $documents = false,
        public bool $structuredOutput = false,
        public bool $jsonMode = false,
        public bool $promptCaching = false,
        public bool $systemMessages = true,
        public int $maxContextWindow = 4096,
        public int $maxOutputTokens = 4096,
        public array $supportedMediaTypes = [],
        public array $custom = [], // Provider-specific capabilities
    ) {}
}
```

#### Model Definition

```php
class Model extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $provider,
        public int $contextWindow,
        public int $maxOutputTokens,
        public ?float $inputCostPer1kTokens = null,
        public ?float $outputCostPer1kTokens = null,
        public ?ProviderCapabilities $capabilities = null,
        public array $metadata = [],
    ) {}
    
    public function estimateCost(int $inputTokens, int $outputTokens): ?float
    {
        if ($this->inputCostPer1kTokens === null) {
            return null;
        }
        
        return (($inputTokens / 1000) * $this->inputCostPer1kTokens)
             + (($outputTokens / 1000) * $this->outputCostPer1kTokens);
    }
}
```

#### Provider Registry

```php
interface ProviderRegistryContract
{
    public function register(string $id, ProviderContract|string $provider): void;
    public function get(string $id): ProviderContract;
    public function has(string $id): bool;
    public function all(): Collection;
    public function default(): ProviderContract;
    public function setDefault(string $id): void;
}
```

#### FakeProvider (Testing)

```php
class FakeProvider implements ProviderContract
{
    private array $responses = [];
    private array $recordedRequests = [];
    
    /**
     * Queue a response to be returned.
     */
    public function addResponse(ChatResponse|StreamedResponse|Closure $response): static;
    
    /**
     * Queue multiple responses.
     */
    public function addResponses(array $responses): static;
    
    /**
     * Set a response factory for dynamic responses.
     */
    public function respondWith(Closure $factory): static;
    
    /**
     * Get all recorded requests.
     */
    public function recordedRequests(): array;
    
    /**
     * Assert a request was made.
     */
    public function assertSent(Closure $callback): void;
    
    /**
     * Assert request count.
     */
    public function assertSentCount(int $count): void;
    
    /**
     * Assert no requests were made.
     */
    public function assertNothingSent(): void;
    
    /**
     * Create with deterministic responses for common scenarios.
     */
    public static function fake(array $responses = []): static;
    
    /**
     * Create that always returns a simple text response.
     */
    public static function text(string $content): static;
    
    /**
     * Create that always requests tool calls.
     */
    public static function withToolCalls(array $toolCalls): static;
}

// Usage in tests
it('sends messages to the provider', function () {
    $fake = FakeProvider::fake([
        ChatResponse::make('Hello! How can I help you?'),
    ]);
    
    Cortex::provider()->swap($fake);
    
    $response = Cortex::chat()->send(
        ChatRequest::make()->message('Hello')
    );
    
    expect($response->content())->toBe('Hello! How can I help you?');
    
    $fake->assertSentCount(1);
    $fake->assertSent(fn ($request) => 
        $request->messages->first()->content === 'Hello'
    );
});
```

#### Initial Provider: AWS Bedrock (Converse API)

```php
// config/cortex.php
'providers' => [
    'bedrock' => [
        'driver' => 'bedrock',
        'region' => env('AWS_REGION', 'us-east-1'),
        'version' => 'latest',
        'credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            // Or use default credential provider chain
        ],
        'default_model' => 'anthropic.claude-3-5-sonnet-20241022-v2:0',
        'models' => [
            // Override or add model definitions
        ],
    ],
],
```

---

### Schema Plugin

**Purpose:** JSON Schema builder for tool parameters, structured output, and validation.

#### Schema Types

```php
abstract class Schema
{
    abstract public function toJsonSchema(): array;
    abstract public function validate(mixed $value): ValidationResult;
    abstract public function cast(mixed $value): mixed;
    
    // Factory methods
    public static function string(): StringSchema;
    public static function number(): NumberSchema;
    public static function integer(): IntegerSchema;
    public static function boolean(): BooleanSchema;
    public static function array(Schema $items): ArraySchema;
    public static function object(): ObjectSchema;
    public static function enum(array $values): EnumSchema;
    public static function anyOf(Schema ...$schemas): UnionSchema;
    public static function oneOf(Schema ...$schemas): UnionSchema;
    public static function nullable(Schema $schema): NullableSchema;
    
    // From existing definitions
    public static function fromJsonSchema(array $schema): Schema;
    public static function fromDataClass(string $class): ObjectSchema;
}
```

#### String Schema

```php
class StringSchema extends Schema
{
    public function minLength(int $length): static;
    public function maxLength(int $length): static;
    public function pattern(string $regex): static;
    public function format(string $format): static; // email, uri, date-time, etc.
    public function description(string $description): static;
    public function default(string $value): static;
    public function examples(string ...$examples): static;
}
```

#### Number Schema

```php
class NumberSchema extends Schema
{
    public function minimum(float $value): static;
    public function maximum(float $value): static;
    public function exclusiveMinimum(float $value): static;
    public function exclusiveMaximum(float $value): static;
    public function multipleOf(float $value): static;
    public function description(string $description): static;
    public function default(float $value): static;
}
```

#### Object Schema

```php
class ObjectSchema extends Schema
{
    public function property(string $name, Schema $schema): static;
    public function properties(array $properties): static;
    public function required(string ...$names): static;
    public function additionalProperties(bool|Schema $value): static;
    public function description(string $description): static;
    
    // Nested object helper
    public function nested(string $name, Closure $callback): static;
}
```

#### Array Schema

```php
class ArraySchema extends Schema
{
    public function items(Schema $schema): static;
    public function minItems(int $count): static;
    public function maxItems(int $count): static;
    public function uniqueItems(bool $unique = true): static;
    public function description(string $description): static;
}
```

#### Fluent Building Example

```php
$schema = Schema::object()
    ->property('name', Schema::string()->minLength(1)->maxLength(100))
    ->property('email', Schema::string()->format('email'))
    ->property('age', Schema::integer()->minimum(0)->maximum(150))
    ->property('tags', Schema::array(Schema::string())->maxItems(10))
    ->property('address', Schema::object()
        ->property('street', Schema::string())
        ->property('city', Schema::string())
        ->property('country', Schema::string())
        ->required('city', 'country'))
    ->required('name', 'email');
```

#### Class-Based Schemas (via Attributes)

```php
use Cortex\Schema\Attributes\SchemaProperty;
use Cortex\Schema\Attributes\SchemaRequired;

#[SchemaRequired(['name', 'email'])]
class UserInput extends Data
{
    #[SchemaProperty(minLength: 1, maxLength: 100)]
    public string $name;
    
    #[SchemaProperty(format: 'email')]
    public string $email;
    
    #[SchemaProperty(minimum: 0, maximum: 150)]
    public ?int $age = null;
    
    /** @var string[] */
    #[SchemaProperty(maxItems: 10)]
    public array $tags = [];
}

// Convert to schema
$schema = Schema::fromDataClass(UserInput::class);
```

#### Validation Result

```php
class ValidationResult extends Data
{
    public function __construct(
        public bool $valid,
        public array $errors = [],
    ) {}
    
    public function throw(): void
    {
        if (!$this->valid) {
            throw SchemaValidationException::withErrors($this->errors);
        }
    }
}
```

---

### Chat Plugin

**Purpose:** Core chat completion functionality with synchronous, streaming, and broadcasting support.

#### Message Types

```php
enum MessageRole: string
{
    case System = 'system';
    case User = 'user';
    case Assistant = 'assistant';
    case Tool = 'tool';
}

abstract class Content
{
    abstract public function toArray(): array;
}

class TextContent extends Content
{
    public function __construct(
        public readonly string $text,
    ) {}
}

class ImageContent extends Content
{
    public function __construct(
        public readonly string $source, // base64 or URL
        public readonly string $mediaType,
        public readonly SourceType $sourceType = SourceType::Base64,
    ) {}
    
    public static function fromBase64(string $data, string $mediaType): static;
    public static function fromUrl(string $url): static;
    public static function fromPath(string $path): static;
}

class DocumentContent extends Content
{
    public function __construct(
        public readonly string $source,
        public readonly string $mediaType,
        public readonly ?string $name = null,
    ) {}
    
    public static function fromBase64(string $data, string $mediaType, ?string $name = null): static;
    public static function fromPath(string $path): static;
}

class ToolUseContent extends Content
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $input,
    ) {}
}

class ToolResultContent extends Content
{
    public function __construct(
        public readonly string $toolUseId,
        public readonly mixed $result,
        public readonly bool $isError = false,
    ) {}
}
```

#### Message

```php
class Message extends Data
{
    public function __construct(
        public MessageRole $role,
        public array $content, // Array of Content objects
        public ?string $name = null,
        public array $metadata = [],
    ) {}
    
    // Factory methods
    public static function system(string $content): static;
    public static function user(string|Content|array $content): static;
    public static function assistant(string|Content|array $content): static;
    public static function toolResult(string $toolUseId, mixed $result, bool $isError = false): static;
    
    // Convenience methods
    public function text(): ?string; // Extract text content
    public function images(): array; // Extract image contents
    public function toolCalls(): array; // Extract tool use contents
}
```

#### Message Collection

```php
class MessageCollection implements Arrayable, Countable, IteratorAggregate
{
    public function add(Message $message): static;
    public function push(Message ...$messages): static;
    public function prepend(Message $message): static;
    public function system(string $content): static;
    public function user(string|Content|array $content): static;
    public function assistant(string|Content|array $content): static;
    
    // Query methods
    public function last(): ?Message;
    public function first(): ?Message;
    public function byRole(MessageRole $role): static;
    public function withoutSystem(): static;
    
    // Token management helpers
    public function estimateTokens(ProviderContract $provider): int;
    public function truncateToTokens(int $maxTokens, ProviderContract $provider): static;
}
```

#### Chat Request

```php
class ChatRequest extends Data
{
    public function __construct(
        public MessageCollection $messages,
        public ?string $systemPrompt = null,
        public ?string $model = null,
        public ChatOptions $options = new ChatOptions(),
        public ?ToolCollection $tools = null, // From Tool plugin
        public ?Schema $responseSchema = null, // From StructuredOutput plugin
        public array $metadata = [],
    ) {}
    
    // Fluent builder
    public static function make(): ChatRequestBuilder;
}

class ChatRequestBuilder
{
    public function system(string $prompt): static;
    public function message(string|Message $message): static;
    public function messages(array|MessageCollection $messages): static;
    public function model(string $model): static;
    public function options(ChatOptions|array $options): static;
    public function tools(ToolCollection|array $tools): static;
    public function responseSchema(Schema $schema): static;
    public function metadata(array $metadata): static;
    public function build(): ChatRequest;
    
    // Shorthand for build()->send()
    public function send(): ChatResponse;
    public function stream(): StreamedResponse;
}

class ChatOptions extends Data
{
    public function __construct(
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public ?float $topP = null,
        public ?int $topK = null,
        public array $stopSequences = [],
        public ?string $toolChoice = null, // 'auto', 'any', 'none', or tool name
        public array $providerOptions = [], // Pass-through for provider-specific options
    ) {}
}
```

#### Chat Response

```php
class ChatResponse extends Data
{
    public function __construct(
        public Message $message,
        public Usage $usage,
        public StopReason $stopReason,
        public ?string $model = null,
        public array $metadata = [],
    ) {}
    
    // Convenience methods
    public function content(): string;
    public function toolCalls(): array;
    public function hasToolCalls(): bool;
    public function firstToolCall(): ?ToolUseContent;
}

class Usage extends Data
{
    public function __construct(
        public int $inputTokens,
        public int $outputTokens,
        public ?int $cacheReadTokens = null,
        public ?int $cacheWriteTokens = null,
    ) {}
    
    public function totalTokens(): int;
    public function estimateCost(Model $model): ?float;
}

enum StopReason: string
{
    case EndTurn = 'end_turn';
    case MaxTokens = 'max_tokens';
    case StopSequence = 'stop_sequence';
    case ToolUse = 'tool_use';
    case ContentFiltered = 'content_filtered';
}
```

#### Streaming

```php
class StreamedResponse implements IteratorAggregate
{
    /**
     * Iterate over stream chunks.
     * @return Generator<StreamChunk>
     */
    public function getIterator(): Generator;
    
    /**
     * Collect all chunks into a final response.
     */
    public function collect(): ChatResponse;
    
    /**
     * Process chunks with a callback.
     */
    public function each(Closure $callback): ChatResponse;
    
    /**
     * Get text content as it streams.
     * @return Generator<string>
     */
    public function text(): Generator;
}

class StreamChunk extends Data
{
    public function __construct(
        public StreamChunkType $type,
        public ?string $text = null,
        public ?ToolUseContent $toolUse = null,
        public ?Usage $usage = null,
        public ?StopReason $stopReason = null,
        public int $index = 0,
    ) {}
}

enum StreamChunkType: string
{
    case TextDelta = 'text_delta';
    case ToolUseStart = 'tool_use_start';
    case ToolUseDelta = 'tool_use_delta';
    case MessageComplete = 'message_complete';
}
```

#### Broadcasting

Broadcasting supports both Laravel Echo (WebSockets) and Server-Sent Events:

```php
interface BroadcasterContract
{
    public function broadcast(string $channel, StreamedResponse $stream): ChatResponse;
}

// Laravel Echo broadcaster
class EchoBroadcaster implements BroadcasterContract
{
    public function broadcast(string $channel, StreamedResponse $stream): ChatResponse
    {
        foreach ($stream as $chunk) {
            broadcast(new ChatStreamChunkEvent($channel, $chunk))->toOthers();
        }
        
        $response = $stream->collect();
        broadcast(new ChatStreamCompleteEvent($channel, $response))->toOthers();
        
        return $response;
    }
}

// SSE broadcaster
class SseBroadcaster implements BroadcasterContract
{
    public function broadcast(string $channel, StreamedResponse $stream): ChatResponse
    {
        // Returns a Symfony StreamedResponse for SSE
    }
}

// Usage
$response = Cortex::chat()
    ->broadcast('chat.conversation-123', $request);

// Or get the SSE response directly
return Cortex::chat()
    ->sse($request); // Returns StreamedResponse for SSE
```

Configuration:

```php
// config/cortex.php
'chat' => [
    'default_model' => null, // Use provider default
    'default_options' => [
        'temperature' => 0.7,
        'max_tokens' => 4096,
    ],
    
    'broadcasting' => [
        'driver' => 'echo', // 'echo' or 'sse'
        'echo' => [
            'connection' => null, // Use default
        ],
        'sse' => [
            'retry' => 3000, // Retry interval in ms
        ],
    ],
],
```

#### Chat Client

```php
interface ChatClientContract
{
    /**
     * Send a synchronous chat request.
     */
    public function send(ChatRequest $request): ChatResponse;
    
    /**
     * Stream a chat response.
     */
    public function stream(ChatRequest $request): StreamedResponse;
    
    /**
     * Broadcast a streamed response to a channel.
     */
    public function broadcast(string $channel, ChatRequest $request): ChatResponse;
    
    /**
     * Get an SSE response.
     */
    public function sse(ChatRequest $request): SymfonyStreamedResponse;
    
    /**
     * Use a specific provider for this request.
     */
    public function using(string|ProviderContract $provider): static;
}
```

---

## Optional Plugins

### Tool Plugin

**Purpose:** Tool/function calling support for LLM interactions.

#### Tool Contract

```php
interface ToolContract
{
    /**
     * Unique tool name (used in LLM calls).
     */
    public function name(): string;
    
    /**
     * Human-readable description for the LLM.
     */
    public function description(): string;
    
    /**
     * Input parameter schema.
     */
    public function inputSchema(): Schema;
    
    /**
     * Optional output schema for structured results.
     */
    public function outputSchema(): ?Schema;
    
    /**
     * Execute the tool with validated input.
     */
    public function execute(array $input, ToolContext $context): ToolResult;
    
    /**
     * Timeout in seconds (null for no timeout).
     */
    public function timeout(): ?int;
}
```

#### Tool Context

```php
class ToolContext extends Data
{
    public function __construct(
        public ?string $conversationId = null,
        public ?string $agentId = null,
        public ?string $tenantId = null,
        public ?Message $triggeringMessage = null,
        public array $metadata = [],
    ) {}
}
```

#### Tool Result

```php
class ToolResult extends Data
{
    public function __construct(
        public bool $success,
        public mixed $output,
        public ?string $error = null,
        public bool $shouldContinue = true, // Allow tool to signal loop termination
        public array $metadata = [],
    ) {}
    
    public static function success(mixed $output, array $metadata = []): static;
    public static function error(string $error, array $metadata = []): static;
    public static function stop(mixed $output, array $metadata = []): static; // Stop agent loop
}
```

#### Creating Tools

**Class-Based (Autodiscovered):**

```php
use Cortex\Tool\Attributes\Tool;
use Cortex\Tool\Attributes\ToolParameter;

#[Tool(
    name: 'get_weather',
    description: 'Get current weather for a location'
)]
class GetWeatherTool implements ToolContract
{
    public function inputSchema(): Schema
    {
        return Schema::object()
            ->property('location', Schema::string()->description('City name'))
            ->property('unit', Schema::enum(['celsius', 'fahrenheit'])->default('celsius'))
            ->required('location');
    }
    
    public function outputSchema(): ?Schema
    {
        return Schema::object()
            ->property('temperature', Schema::number())
            ->property('conditions', Schema::string())
            ->property('humidity', Schema::number());
    }
    
    public function execute(array $input, ToolContext $context): ToolResult
    {
        // Fetch weather...
        return ToolResult::success([
            'temperature' => 22.5,
            'conditions' => 'Partly cloudy',
            'humidity' => 65,
        ]);
    }
    
    public function timeout(): ?int
    {
        return 30;
    }
}
```

**Closure-Based:**

```php
$tool = Tool::make('search_database')
    ->description('Search the product database')
    ->input(
        Schema::object()
            ->property('query', Schema::string())
            ->property('limit', Schema::integer()->default(10))
            ->required('query')
    )
    ->handler(function (array $input, ToolContext $context) {
        $results = Product::search($input['query'])
            ->take($input['limit'])
            ->get();
        
        return ToolResult::success($results->toArray());
    })
    ->timeout(60);
```

**From Invokable Class:**

```php
class CalculatorTool
{
    public function __invoke(string $expression): array
    {
        $result = eval("return {$expression};");
        return ['result' => $result];
    }
}

$tool = Tool::fromInvokable(CalculatorTool::class)
    ->name('calculator')
    ->description('Evaluate mathematical expressions');
```

#### Tool Collection

```php
class ToolCollection implements Arrayable, Countable, IteratorAggregate
{
    public function add(ToolContract $tool): static;
    public function remove(string $name): static;
    public function get(string $name): ?ToolContract;
    public function has(string $name): bool;
    public function names(): array;
    
    // For LLM API calls
    public function toToolDefinitions(): array;
}
```

#### Tool Registry

```php
interface ToolRegistryContract
{
    public function register(ToolContract|string $tool): void;
    public function get(string $name): ToolContract;
    public function has(string $name): bool;
    public function all(): Collection;
    public function collection(string ...$names): ToolCollection;
    
    /**
     * Auto-discover tools from configured paths.
     */
    public function discover(): void;
}
```

Configuration:

```php
// config/cortex.php
'tool' => [
    'discovery' => [
        'enabled' => true,
        'paths' => [
            app_path('Tools'),
        ],
    ],
    
    'defaults' => [
        'timeout' => 30,
    ],
    
    // Register specific tools
    'tools' => [
        GetWeatherTool::class,
        SearchDatabaseTool::class,
    ],
],
```

---

### Structured Output Plugin

**Purpose:** Enforce structured/typed responses from LLMs.

#### Structured Output Handler

```php
interface StructuredOutputContract
{
    /**
     * Request structured output matching a schema.
     */
    public function generate(ChatRequest $request, Schema $schema): StructuredResponse;
    
    /**
     * Request structured output matching a Data class.
     */
    public function generateAs(ChatRequest $request, string $dataClass): object;
}

class StructuredResponse extends Data
{
    public function __construct(
        public mixed $data,
        public Schema $schema,
        public bool $valid,
        public array $validationErrors,
        public ChatResponse $rawResponse,
    ) {}
    
    public function toData(string $class): object;
    public function toArray(): array;
    public function throw(): static; // Throw if invalid
}
```

#### Usage

```php
// With schema
$schema = Schema::object()
    ->property('sentiment', Schema::enum(['positive', 'negative', 'neutral']))
    ->property('confidence', Schema::number()->minimum(0)->maximum(1))
    ->property('keywords', Schema::array(Schema::string()))
    ->required('sentiment', 'confidence');

$response = Cortex::structuredOutput()->generate(
    ChatRequest::make()
        ->message('Analyze: "This product is amazing!"')
        ->build(),
    $schema
);

$data = $response->toArray();
// ['sentiment' => 'positive', 'confidence' => 0.95, 'keywords' => ['amazing', 'product']]

// With Data class
class SentimentAnalysis extends Data
{
    public function __construct(
        public string $sentiment,
        public float $confidence,
        public array $keywords,
    ) {}
}

$analysis = Cortex::structuredOutput()->generateAs(
    ChatRequest::make()->message('Analyze: "This product is amazing!"')->build(),
    SentimentAnalysis::class
);
// Returns SentimentAnalysis object
```

#### Provider-Specific Behavior

The structured output plugin adapts to provider capabilities:

1. **Native structured output** (if supported): Uses provider's native JSON schema mode
2. **JSON mode fallback**: If provider supports JSON mode but not schemas, adds schema to prompt
3. **Prompt-based fallback**: Injects schema into prompt and parses response

```php
// config/cortex.php
'structured_output' => [
    'strategy' => 'auto', // 'auto', 'native', 'json_mode', 'prompt'
    'validation' => [
        'enabled' => true,
        'throw_on_invalid' => false,
    ],
    'retry' => [
        'enabled' => true,
        'max_attempts' => 2,
    ],
],
```

---

### MCP Plugin (Model Context Protocol)

**Purpose:** Integration with MCP servers for extended tool capabilities.

#### MCP Server Configuration

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
    public function resources(): ResourceCollection;
    public function prompts(): PromptCollection;
}

enum McpTransport: string
{
    case Stdio = 'stdio';
    case Sse = 'sse';
    case Http = 'http';
}
```

#### Server Registration

**Config-Based:**

```php
// config/cortex.php
'mcp' => [
    'servers' => [
        'filesystem' => [
            'command' => 'npx',
            'args' => ['-y', '@modelcontextprotocol/server-filesystem', '/path/to/dir'],
            'transport' => 'stdio',
        ],
        'custom' => [
            'url' => 'http://localhost:3000/mcp',
            'transport' => 'sse',
            'headers' => [
                'Authorization' => 'Bearer ' . env('MCP_TOKEN'),
            ],
        ],
    ],
],
```

**Class-Based (Autodiscovered):**

```php
use Cortex\Mcp\Attributes\McpServer;

#[McpServer(id: 'my-server')]
class MyMcpServer implements McpServerConfigContract
{
    public function id(): string
    {
        return 'my-server';
    }
    
    public function transport(): McpTransport
    {
        return McpTransport::Stdio;
    }
    
    public function command(): string
    {
        return 'node';
    }
    
    public function args(): array
    {
        return [base_path('mcp-servers/my-server/index.js')];
    }
}
```

**Dynamic:**

```php
Cortex::mcp()->register(
    McpServer::stdio('my-server', 'node', ['server.js'])
);

Cortex::mcp()->register(
    McpServer::sse('remote-server', 'https://api.example.com/mcp')
        ->withHeaders(['Authorization' => 'Bearer token'])
);
```

#### Using MCP Tools

```php
// Get tools from MCP server
$tools = Cortex::mcp()->server('filesystem')->tools();

// Use in chat
$response = Cortex::chat()->send(
    ChatRequest::make()
        ->message('List files in the project directory')
        ->tools($tools)
        ->build()
);

// Or merge with local tools
$allTools = ToolCollection::make()
    ->merge(Cortex::tools()->collection('local_tool_1', 'local_tool_2'))
    ->merge(Cortex::mcp()->server('filesystem')->tools());
```

---

### Agent Plugin

**Purpose:** Autonomous agents with tool use, memory, and agentic loops.

#### Agent Contract

```php
interface AgentContract
{
    public function id(): string;
    public function name(): string;
    public function description(): string;
    
    /**
     * System prompt or Prompt object.
     */
    public function systemPrompt(): string|Prompt;
    
    /**
     * Available tools for this agent.
     */
    public function tools(): ToolCollection;
    
    /**
     * Model to use.
     */
    public function model(): ?string;
    
    /**
     * Provider to use.
     */
    public function provider(): ?string;
    
    /**
     * Maximum iterations for agentic loop.
     */
    public function maxIterations(): int;
    
    /**
     * Memory strategy for conversation context.
     */
    public function memory(): ?MemoryContract;
    
    /**
     * Run the agent with input.
     */
    public function run(string|array $input, ?AgentContext $context = null): AgentResponse;
    
    /**
     * Run the agent asynchronously.
     */
    public function runAsync(string|array $input, ?AgentContext $context = null): PendingAgentRun;
}
```

#### Agent Loop Strategies

```php
enum AgentLoopStrategy: string
{
    case ReAct = 'react';           // Reasoning + Acting
    case PlanAndExecute = 'plan';    // Plan first, then execute
    case Simple = 'simple';          // Basic tool loop
    case Custom = 'custom';          // Implement your own
}

interface AgentLoopContract
{
    public function execute(
        AgentContract $agent,
        string|array $input,
        AgentContext $context
    ): AgentResponse;
}
```

#### Memory Strategies

```php
interface MemoryContract
{
    /**
     * Add a message to memory.
     */
    public function add(Message $message): void;
    
    /**
     * Get messages to include in context.
     */
    public function messages(): MessageCollection;
    
    /**
     * Clear memory.
     */
    public function clear(): void;
    
    /**
     * Get token count of current memory.
     */
    public function tokenCount(ProviderContract $provider): int;
}

// Implementations
class BufferMemory implements MemoryContract
{
    // Keep all messages
}

class SlidingWindowMemory implements MemoryContract
{
    public function __construct(
        private int $windowSize = 10,
        private bool $keepSystemMessage = true,
    ) {}
}

class TokenLimitMemory implements MemoryContract
{
    public function __construct(
        private int $maxTokens = 4000,
        private string $truncationStrategy = 'oldest', // 'oldest', 'middle'
    ) {}
}

class SummaryMemory implements MemoryContract
{
    // Summarize older messages to save tokens
    public function __construct(
        private int $summarizeAfter = 10,
        private ?AgentContract $summarizerAgent = null,
    ) {}
}
```

#### RAG Integration

RAG is implemented via a Retriever interface, allowing flexibility:

```php
interface RetrieverContract
{
    /**
     * Retrieve relevant content for a query.
     */
    public function retrieve(string $query, int $limit = 5): RetrievedContent;
}

class RetrievedContent extends Data
{
    public function __construct(
        /** @var RetrievedItem[] */
        public array $items,
        public array $metadata = [],
    ) {}
    
    public function toContext(): string;
    public function isEmpty(): bool;
}

class RetrievedItem extends Data
{
    public function __construct(
        public string $content,
        public float $score,
        public array $metadata = [],
    ) {}
}

// Example implementations (not included in core, but easy to create)
class VectorStoreRetriever implements RetrieverContract
{
    public function __construct(
        private VectorStoreContract $store,
        private EmbeddingProviderContract $embeddings,
    ) {}
}

class SqlRetriever implements RetrieverContract
{
    public function __construct(
        private string $table,
        private array $searchColumns,
    ) {}
}

class CallbackRetriever implements RetrieverContract
{
    public function __construct(
        private Closure $callback,
    ) {}
}
```

#### Agent Configuration

```php
// config/cortex.php
'agent' => [
    'discovery' => [
        'enabled' => true,
        'paths' => [
            app_path('Agents'),
        ],
    ],
    
    'defaults' => [
        'max_iterations' => 10,
        'loop_strategy' => 'react',
        'memory' => 'sliding_window',
    ],
    
    'memory' => [
        'sliding_window' => [
            'size' => 10,
        ],
        'token_limit' => [
            'max_tokens' => 4000,
        ],
    ],
],
```

#### Creating Agents

**Class-Based:**

```php
use Cortex\Agent\Attributes\Agent;

#[Agent(
    id: 'research-assistant',
    name: 'Research Assistant',
    description: 'Helps with research tasks'
)]
class ResearchAssistantAgent implements AgentContract
{
    public function __construct(
        private ToolRegistryContract $tools,
    ) {}
    
    public function systemPrompt(): string
    {
        return <<<PROMPT
        You are a research assistant. Use the available tools to find information 
        and provide comprehensive answers to research questions.
        PROMPT;
    }
    
    public function tools(): ToolCollection
    {
        return $this->tools->collection('web_search', 'summarize', 'cite_sources');
    }
    
    public function model(): ?string
    {
        return 'anthropic.claude-3-5-sonnet-20241022-v2:0';
    }
    
    public function provider(): ?string
    {
        return 'bedrock';
    }
    
    public function maxIterations(): int
    {
        return 15;
    }
    
    public function memory(): ?MemoryContract
    {
        return new SlidingWindowMemory(20);
    }
    
    public function run(string|array $input, ?AgentContext $context = null): AgentResponse
    {
        // Use default loop implementation
        return app(AgentLoopContract::class)->execute($this, $input, $context ?? new AgentContext());
    }
    
    public function runAsync(string|array $input, ?AgentContext $context = null): PendingAgentRun
    {
        return PendingAgentRun::dispatch($this, $input, $context);
    }
}
```

**Builder-Based:**

```php
$agent = Agent::make('quick-assistant')
    ->name('Quick Assistant')
    ->description('A simple helpful assistant')
    ->systemPrompt('You are a helpful assistant.')
    ->tools(['calculator', 'web_search'])
    ->model('anthropic.claude-3-5-sonnet-20241022-v2:0')
    ->maxIterations(5)
    ->memory(SlidingWindowMemory::make(10))
    ->retriever(new CallbackRetriever(function ($query) {
        return RetrievedContent::fromArray(
            Document::search($query)->take(5)->get()
        );
    }));

// Use it
$response = $agent->run('What is 25 * 48?');
```

#### Agent Response

```php
class AgentResponse extends Data
{
    public function __construct(
        public string $output,
        public int $iterations,
        public array $toolCalls,
        public MessageCollection $messages,
        public Usage $totalUsage,
        public float $duration,
        public array $metadata = [],
    ) {}
    
    public function successful(): bool;
    public function reachedMaxIterations(): bool;
}
```

#### Async Agent Execution

```php
class PendingAgentRun implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(
        public AgentContract|string $agent,
        public string|array $input,
        public ?AgentContext $context = null,
        public ?string $callbackChannel = null,
    ) {}
    
    /**
     * Get the run ID for tracking.
     */
    public function id(): string;
    
    /**
     * Set the queue to use.
     */
    public function onQueue(string $queue): static;
    
    /**
     * Broadcast progress to a channel.
     */
    public function broadcastTo(string $channel): static;
    
    /**
     * Get run status.
     */
    public static function status(string $runId): AgentRunStatus;
    
    /**
     * Get run result (if complete).
     */
    public static function result(string $runId): ?AgentResponse;
}

// Usage
$run = ResearchAssistantAgent::make()->runAsync('Research quantum computing');

$runId = $run
    ->onQueue('agents')
    ->broadcastTo('agent-runs.' . $user->id)
    ->dispatch();

// Later, check status
$status = PendingAgentRun::status($runId);
if ($status->isComplete()) {
    $response = PendingAgentRun::result($runId);
}
```

---

### Workflow Plugin

**Purpose:** Complex multi-step workflows with branching, looping, and state management.

#### Workflow Definition

```php
interface WorkflowContract
{
    public function id(): string;
    public function name(): string;
    public function description(): string;
    
    /**
     * Build and return the workflow definition.
     */
    public function definition(): WorkflowDefinition;
    
    /**
     * Run the workflow.
     */
    public function run(array $input, ?WorkflowContext $context = null): WorkflowResult;
    
    /**
     * Run the workflow asynchronously.
     */
    public function runAsync(array $input, ?WorkflowContext $context = null): PendingWorkflowRun;
}

class WorkflowDefinition extends Data
{
    public function __construct(
        /** @var array<string, NodeContract> */
        public array $nodes,
        /** @var Edge[] */
        public array $edges,
        public string $entryPoint,
        public array $exitPoints = [],
    ) {}
}
```

#### Node Types

```php
interface NodeContract
{
    public function id(): string;
    public function execute(array $input, WorkflowState $state): NodeResult;
}

class NodeResult extends Data
{
    public function __construct(
        public array $output,
        public ?string $nextNode = null, // Override default edge
        public bool $shouldPause = false,
        public ?string $pauseReason = null,
        public array $metadata = [],
    ) {}
}

// Built-in node types
class AgentNode implements NodeContract
{
    public function __construct(
        public string $id,
        public AgentContract|string $agent,
        public ?string $inputKey = null,  // Key in state to use as input
        public ?string $outputKey = null, // Key in state to store output
    ) {}
}

class ToolNode implements NodeContract
{
    public function __construct(
        public string $id,
        public ToolContract|string $tool,
        public array|Closure $inputMapping,
        public ?string $outputKey = null,
    ) {}
}

class ConditionNode implements NodeContract
{
    public function __construct(
        public string $id,
        public Closure $condition,
        public string $trueNode,
        public string $falseNode,
    ) {}
}

class ParallelNode implements NodeContract
{
    public function __construct(
        public string $id,
        /** @var NodeContract[] */
        public array $nodes,
        public string $mergeStrategy = 'all', // 'all', 'any', 'custom'
        public ?Closure $merger = null,
    ) {}
}

class LoopNode implements NodeContract
{
    public function __construct(
        public string $id,
        public NodeContract|string $body,
        public Closure $condition, // Continue while true
        public int $maxIterations = 100,
    ) {}
}

class HumanInputNode implements NodeContract
{
    public function __construct(
        public string $id,
        public string $prompt,
        public ?Schema $inputSchema = null,
        public ?int $timeout = null, // Seconds before workflow times out
    ) {}
}

class SubWorkflowNode implements NodeContract
{
    public function __construct(
        public string $id,
        public WorkflowContract|string $workflow,
        public array|Closure $inputMapping,
        public ?string $outputKey = null,
    ) {}
}

class CallbackNode implements NodeContract
{
    public function __construct(
        public string $id,
        public Closure $callback,
    ) {}
}
```

#### Edges

```php
class Edge extends Data
{
    public function __construct(
        public string $from,
        public string $to,
        public ?Closure $condition = null,
        public int $priority = 0, // Higher priority edges evaluated first
    ) {}
}
```

#### Workflow State

```php
class WorkflowState extends Data
{
    public function __construct(
        public string $workflowId,
        public string $runId,
        public string $currentNode,
        public WorkflowStatus $status,
        public array $data = [],      // Shared data between nodes
        public array $history = [],    // Execution trace
        public ?string $pauseReason = null,
        public ?\DateTimeInterface $startedAt = null,
        public ?\DateTimeInterface $pausedAt = null,
        public ?\DateTimeInterface $completedAt = null,
    ) {}
    
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value): static;
    public function has(string $key): bool;
}

enum WorkflowStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
```

#### Workflow Builder

```php
class WorkflowBuilder
{
    public static function make(string $id): static;
    
    public function name(string $name): static;
    public function description(string $description): static;
    
    // Add nodes
    public function addNode(NodeContract $node): static;
    public function agent(string $id, AgentContract|string $agent): static;
    public function tool(string $id, ToolContract|string $tool, array|Closure $input): static;
    public function condition(string $id, Closure $condition, string $trueNode, string $falseNode): static;
    public function parallel(string $id, array $nodes, string $mergeStrategy = 'all'): static;
    public function loop(string $id, NodeContract|string $body, Closure $condition, int $max = 100): static;
    public function humanInput(string $id, string $prompt, ?Schema $schema = null): static;
    public function subWorkflow(string $id, WorkflowContract|string $workflow, array|Closure $input): static;
    public function callback(string $id, Closure $callback): static;
    
    // Add edges
    public function edge(string $from, string $to, ?Closure $condition = null): static;
    public function edges(array $edges): static;
    
    // Set entry/exit
    public function startAt(string $nodeId): static;
    public function endAt(string ...$nodeIds): static;
    
    public function build(): WorkflowDefinition;
}
```

#### Example Workflow

```php
#[Workflow(id: 'content-pipeline')]
class ContentPipelineWorkflow implements WorkflowContract
{
    public function definition(): WorkflowDefinition
    {
        return WorkflowBuilder::make('content-pipeline')
            ->name('Content Pipeline')
            ->description('Research, write, and review content')
            
            // Nodes
            ->agent('research', 'research-assistant')
            ->agent('writer', 'content-writer')
            ->agent('reviewer', 'content-reviewer')
            ->condition('quality-check', 
                fn ($state) => $state->get('review.score') >= 8,
                trueNode: 'publish',
                falseNode: 'revise'
            )
            ->callback('revise', function ($input, $state) {
                $state->set('revision_count', $state->get('revision_count', 0) + 1);
                return NodeResult::make(['feedback' => $state->get('review.feedback')]);
            })
            ->condition('max-revisions',
                fn ($state) => $state->get('revision_count') < 3,
                trueNode: 'writer',
                falseNode: 'human-review'
            )
            ->humanInput('human-review', 'Please review and approve this content:', Schema::object()
                ->property('approved', Schema::boolean())
                ->property('feedback', Schema::string())
            )
            ->callback('publish', function ($input, $state) {
                // Publish logic
                return NodeResult::make(['published' => true, 'url' => '...']);
            })
            
            // Edges
            ->startAt('research')
            ->edge('research', 'writer')
            ->edge('writer', 'reviewer')
            ->edge('reviewer', 'quality-check')
            ->edge('revise', 'max-revisions')
            ->edge('human-review', 'publish', fn ($state) => $state->get('human_input.approved'))
            ->edge('human-review', 'writer', fn ($state) => !$state->get('human_input.approved'))
            ->endAt('publish')
            
            ->build();
    }
}
```

#### Workflow Persistence

Long-running workflows need persistence for pause/resume:

```php
interface WorkflowStateRepositoryContract
{
    public function save(WorkflowState $state): void;
    public function find(string $runId): ?WorkflowState;
    public function findByWorkflow(string $workflowId): Collection;
    public function delete(string $runId): void;
}

// Implementations
class DatabaseWorkflowStateRepository implements WorkflowStateRepositoryContract
{
    // Uses Laravel's database
}

class CacheWorkflowStateRepository implements WorkflowStateRepositoryContract
{
    // Uses Laravel's cache (for short-lived workflows)
}
```

Configuration:

```php
// config/cortex.php
'workflow' => [
    'discovery' => [
        'enabled' => true,
        'paths' => [
            app_path('Workflows'),
        ],
    ],
    
    'persistence' => [
        'driver' => 'database', // 'database', 'cache'
        'table' => 'cortex_workflow_states',
        'ttl' => 86400 * 7, // 7 days for cache driver
    ],
    
    'async' => [
        'queue' => 'workflows',
        'timeout' => 3600, // 1 hour max
    ],
],
```

---

### Guardrail Plugin

**Purpose:** Content safety and policy enforcement.

#### Guardrail Contract

```php
interface GuardrailContract
{
    public function id(): string;
    public function name(): string;
    
    /**
     * Check content against the guardrail.
     */
    public function check(string|Message|MessageCollection $content): GuardrailResult;
    
    /**
     * Get configuration.
     */
    public function config(): array;
}

class GuardrailResult extends Data
{
    public function __construct(
        public bool $passed,
        public array $violations = [],
        public ?string $blockedReason = null,
        public float $confidence = 1.0,
        public array $metadata = [],
    ) {}
    
    public function throw(): void
    {
        if (!$this->passed) {
            throw GuardrailViolationException::withViolations($this->violations);
        }
    }
}

class GuardrailViolation extends Data
{
    public function __construct(
        public string $type,
        public string $description,
        public float $confidence,
        public ?string $span = null, // The offending text
        public array $metadata = [],
    ) {}
}
```

#### Bedrock Guardrail

```php
class BedrockGuardrail implements GuardrailContract
{
    public function __construct(
        private string $guardrailId,
        private string $guardrailVersion = 'DRAFT',
        private array $config = [],
    ) {}
    
    public function check(string|Message|MessageCollection $content): GuardrailResult
    {
        // Call Bedrock Guardrails API
    }
}

// Usage
$guardrail = new BedrockGuardrail(
    guardrailId: 'abc123',
    guardrailVersion: '1',
);

$result = $guardrail->check($userMessage);
if (!$result->passed) {
    // Handle violation
}
```

#### Guardrail Pipeline

Apply multiple guardrails:

```php
class GuardrailPipeline
{
    public function add(GuardrailContract $guardrail): static;
    
    public function check(string|Message|MessageCollection $content): GuardrailPipelineResult;
}

class GuardrailPipelineResult extends Data
{
    public function __construct(
        public bool $passed,
        /** @var array<string, GuardrailResult> */
        public array $results,
    ) {}
    
    public function failedGuardrails(): array;
}
```

#### Chat Integration

```php
// Apply guardrails to chat
$response = Cortex::chat()
    ->withGuardrails(['content-policy', 'pii-filter'])
    ->send($request);

// Or in request
$request = ChatRequest::make()
    ->message($userInput)
    ->guardrails(['content-policy'])
    ->build();
```

Configuration:

```php
// config/cortex.php
'guardrail' => [
    'discovery' => [
        'enabled' => true,
        'paths' => [
            app_path('Guardrails'),
        ],
    ],
    
    'guardrails' => [
        'bedrock-default' => [
            'driver' => 'bedrock',
            'guardrail_id' => env('BEDROCK_GUARDRAIL_ID'),
            'version' => env('BEDROCK_GUARDRAIL_VERSION', 'DRAFT'),
        ],
    ],
    
    // Apply guardrails to all chat requests
    'default' => [
        // 'bedrock-default',
    ],
],
```

---

### Resilience Plugin

**Purpose:** Fault tolerance for LLM API calls.

#### Strategies

```php
interface ResilienceStrategyContract
{
    public function execute(Closure $operation): mixed;
}

class RetryStrategy implements ResilienceStrategyContract
{
    public function __construct(
        public int $maxAttempts = 3,
        public int $delayMs = 1000,
        public float $multiplier = 2.0,
        public int $maxDelayMs = 30000,
        public bool $jitter = true,
        public array $retryOn = [], // Exception classes to retry
    ) {}
}

class CircuitBreakerStrategy implements ResilienceStrategyContract
{
    public function __construct(
        public int $failureThreshold = 5,
        public int $recoveryTimeMs = 60000,
        public int $successThreshold = 2,
    ) {}
    
    public function state(): CircuitState; // Closed, Open, HalfOpen
    public function isOpen(): bool;
}

class TimeoutStrategy implements ResilienceStrategyContract
{
    public function __construct(
        public int $timeoutMs = 30000,
    ) {}
}

class FallbackStrategy implements ResilienceStrategyContract
{
    public function __construct(
        public Closure|ProviderContract|string $fallback,
    ) {}
}

class RateLimiterStrategy implements ResilienceStrategyContract
{
    public function __construct(
        public int $maxRequests = 60,
        public int $perSeconds = 60,
        public ?int $maxTokensPerMinute = null,
    ) {}
}

class BulkheadStrategy implements ResilienceStrategyContract
{
    public function __construct(
        public int $maxConcurrent = 10,
        public int $maxWaitMs = 5000,
    ) {}
}
```

#### Composing Strategies

```php
class ResiliencePolicy
{
    public static function make(): static;
    
    public function retry(int $attempts = 3, int $delayMs = 1000): static;
    public function circuitBreaker(int $threshold = 5, int $recoveryMs = 60000): static;
    public function timeout(int $ms = 30000): static;
    public function fallback(Closure|ProviderContract|string $fallback): static;
    public function rateLimit(int $requests = 60, int $perSeconds = 60): static;
    public function bulkhead(int $maxConcurrent = 10): static;
    
    public function execute(Closure $operation): mixed;
}

// Usage
$policy = ResiliencePolicy::make()
    ->timeout(30000)
    ->retry(3, 1000)
    ->circuitBreaker(5, 60000)
    ->fallback('anthropic'); // Fall back to different provider

$response = $policy->execute(fn () => 
    Cortex::provider('bedrock')->chat($request)
);
```

#### Provider Decoration

```php
// Wrap a provider with resilience
$resilientProvider = Cortex::resilience()->wrap(
    Cortex::provider('bedrock'),
    ResiliencePolicy::make()->retry(3)->timeout(30000)
);

// Or configure globally
// config/cortex.php
'resilience' => [
    'enabled' => true,
    
    'default' => [
        'retry' => [
            'attempts' => 3,
            'delay' => 1000,
            'multiplier' => 2.0,
        ],
        'timeout' => 30000,
    ],
    
    'providers' => [
        'bedrock' => [
            'retry' => ['attempts' => 5],
            'circuit_breaker' => [
                'threshold' => 5,
                'recovery' => 60000,
            ],
            'fallback' => 'openai', // If bedrock fails, try openai
        ],
    ],
],
```

---

### Prompt Plugin

**Purpose:** Prompt templating, management, and versioning.

#### Prompt Definition

```php
interface PromptContract
{
    public function id(): string;
    public function name(): string;
    public function version(): string;
    
    /**
     * Get the prompt template content.
     */
    public function template(): string;
    
    /**
     * Get required variables.
     */
    public function variables(): array;
    
    /**
     * Render the prompt with variables.
     */
    public function render(array $variables = []): string;
    
    /**
     * Validate variables against requirements.
     */
    public function validate(array $variables): ValidationResult;
}
```

#### Prompt Templating

Supports Blade syntax:

```php
class Prompt implements PromptContract
{
    public function __construct(
        public string $id,
        public string $template,
        public array $requiredVariables = [],
        public array $defaults = [],
        public ?string $version = null,
        public array $metadata = [],
    ) {}
    
    public function render(array $variables = []): string
    {
        $merged = array_merge($this->defaults, $variables);
        return Blade::render($this->template, $merged);
    }
}

// Usage
$prompt = new Prompt(
    id: 'summarizer',
    template: <<<'BLADE'
    You are a summarization assistant.
    
    @if($style)
    Use a {{ $style }} style.
    @endif
    
    Summarize the following content in {{ $length }} words or less:
    
    {{ $content }}
    BLADE,
    requiredVariables: ['content', 'length'],
    defaults: ['style' => null],
);

$rendered = $prompt->render([
    'content' => $article,
    'length' => 100,
    'style' => 'professional',
]);
```

#### Prompt Registry

```php
interface PromptRegistryContract
{
    public function register(PromptContract $prompt): void;
    public function get(string $id, ?string $version = null): PromptContract;
    public function has(string $id): bool;
    public function versions(string $id): Collection;
    public function latest(string $id): PromptContract;
}
```

#### File-Based Prompts

Store prompts as files:

```
resources/prompts/
├── summarizer/
│   ├── v1.blade.php
│   ├── v2.blade.php
│   └── prompt.yaml  # Metadata
└── assistant/
    ├── v1.blade.php
    └── prompt.yaml
```

```yaml
# prompt.yaml
id: summarizer
name: Content Summarizer
required_variables:
  - content
  - length
defaults:
  style: null
```

Configuration:

```php
// config/cortex.php
'prompt' => [
    'discovery' => [
        'enabled' => true,
        'paths' => [
            resource_path('prompts'),
        ],
    ],
    
    'caching' => [
        'enabled' => true,
        'ttl' => 3600,
    ],
],
```

---

### Usage Plugin

**Purpose:** Track token usage and costs across all LLM interactions.

#### Usage Tracking

```php
interface UsageTrackerContract
{
    /**
     * Record usage from a response.
     */
    public function record(UsageRecord $record): void;
    
    /**
     * Get usage summary for a period.
     */
    public function summary(
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null,
        array $filters = []
    ): UsageSummary;
    
    /**
     * Get usage by model.
     */
    public function byModel(
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null
    ): Collection;
    
    /**
     * Get usage by tenant.
     */
    public function byTenant(
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $to = null
    ): Collection;
}

class UsageRecord extends Data
{
    public function __construct(
        public string $provider,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public ?float $cost = null,
        public ?string $tenantId = null,
        public ?string $agentId = null,
        public ?string $workflowId = null,
        public ?string $conversationId = null,
        public array $metadata = [],
        public ?\DateTimeInterface $timestamp = null,
    ) {}
}

class UsageSummary extends Data
{
    public function __construct(
        public int $totalInputTokens,
        public int $totalOutputTokens,
        public int $totalRequests,
        public float $totalCost,
        public \DateTimeInterface $from,
        public \DateTimeInterface $to,
        public array $byModel = [],
        public array $byProvider = [],
    ) {}
}
```

#### Budget Limits

```php
interface BudgetContract
{
    public function check(UsageRecord $record): BudgetCheckResult;
    public function remaining(): float;
    public function usage(): float;
    public function limit(): float;
}

class BudgetCheckResult extends Data
{
    public function __construct(
        public bool $allowed,
        public float $remaining,
        public float $percentUsed,
        public ?string $warning = null,
    ) {}
}

// Budget types
class TokenBudget implements BudgetContract
{
    public function __construct(
        public int $maxTokens,
        public string $period = 'daily', // 'hourly', 'daily', 'monthly'
        public ?string $tenantId = null,
    ) {}
}

class CostBudget implements BudgetContract
{
    public function __construct(
        public float $maxCost,
        public string $period = 'monthly',
        public ?string $tenantId = null,
    ) {}
}
```

Configuration:

```php
// config/cortex.php
'usage' => [
    'enabled' => true,
    
    'tracking' => [
        'driver' => 'database', // 'database', 'redis', 'null'
        'table' => 'cortex_usage',
    ],
    
    'budgets' => [
        'global' => [
            'type' => 'cost',
            'limit' => 1000.00,
            'period' => 'monthly',
        ],
        // Tenant-specific budgets can be set programmatically
    ],
    
    'alerts' => [
        'thresholds' => [0.5, 0.75, 0.9, 1.0],
        'channels' => ['mail', 'slack'],
    ],
],
```

---

### Cache Plugin

**Purpose:** Cache LLM responses to reduce costs and latency.

#### Cache Strategies

```php
interface CacheStrategyContract
{
    public function key(ChatRequest $request): string;
    public function shouldCache(ChatRequest $request): bool;
    public function ttl(ChatRequest $request): int;
}

class ExactMatchCache implements CacheStrategyContract
{
    // Cache based on exact message match
    public function key(ChatRequest $request): string
    {
        return hash('sha256', serialize([
            $request->messages->toArray(),
            $request->systemPrompt,
            $request->model,
            $request->options->toArray(),
        ]));
    }
}

class SemanticCache implements CacheStrategyContract
{
    // Cache based on semantic similarity (requires embeddings)
    public function __construct(
        private EmbeddingProviderContract $embeddings,
        private float $similarityThreshold = 0.95,
    ) {}
}
```

#### Cache Decorator

```php
class CachedChatClient implements ChatClientContract
{
    public function __construct(
        private ChatClientContract $client,
        private CacheStrategyContract $strategy,
        private Repository $cache,
    ) {}
    
    public function send(ChatRequest $request): ChatResponse
    {
        if (!$this->strategy->shouldCache($request)) {
            return $this->client->send($request);
        }
        
        $key = $this->strategy->key($request);
        
        return $this->cache->remember(
            $key,
            $this->strategy->ttl($request),
            fn () => $this->client->send($request)
        );
    }
}
```

Configuration:

```php
// config/cortex.php
'cache' => [
    'enabled' => true,
    
    'strategy' => 'exact', // 'exact', 'semantic'
    
    'store' => 'redis', // Laravel cache store
    
    'ttl' => 3600, // Default TTL
    
    // Don't cache if these conditions are met
    'skip_if' => [
        'has_tools' => true,
        'temperature_above' => 0.5,
    ],
    
    'semantic' => [
        'threshold' => 0.95,
        'embeddings_provider' => 'openai',
    ],
],
```

---

### Context Manager Plugin

**Purpose:** Automatic context window management for long conversations.

#### Context Strategies

```php
interface ContextStrategyContract
{
    /**
     * Reduce messages to fit within token limit.
     */
    public function reduce(
        MessageCollection $messages,
        int $maxTokens,
        ProviderContract $provider
    ): MessageCollection;
}

class TruncateOldestStrategy implements ContextStrategyContract
{
    public function __construct(
        private bool $preserveSystemMessage = true,
        private int $preserveRecentCount = 2,
    ) {}
}

class SummarizeStrategy implements ContextStrategyContract
{
    public function __construct(
        private AgentContract|string $summarizer,
        private int $summarizeThreshold = 10, // Messages before summarizing
    ) {}
}

class ImportanceStrategy implements ContextStrategyContract
{
    public function __construct(
        private Closure $scorer, // Score message importance
    ) {}
}
```

#### Context Manager

```php
interface ContextManagerContract
{
    /**
     * Prepare messages to fit within context window.
     */
    public function prepare(
        MessageCollection $messages,
        ?string $model = null,
        ?int $reserveTokens = null // Reserve for response
    ): MessageCollection;
    
    /**
     * Estimate if messages fit within context.
     */
    public function fits(
        MessageCollection $messages,
        ?string $model = null
    ): bool;
    
    /**
     * Get token count for messages.
     */
    public function countTokens(MessageCollection $messages): int;
}
```

Configuration:

```php
// config/cortex.php
'context' => [
    'strategy' => 'truncate', // 'truncate', 'summarize', 'importance'
    
    'reserve_tokens' => 4096, // Reserve for response
    
    'truncate' => [
        'preserve_system' => true,
        'preserve_recent' => 2,
    ],
    
    'summarize' => [
        'threshold' => 10,
        'agent' => 'context-summarizer',
    ],
],
```

---

## Plugin Dependency Graph

```
                        ┌─────────────────────┐
                        │       Schema        │
                        │       (core)        │
                        └──────────┬──────────┘
                                   │
              ┌────────────────────┼────────────────────┐
              │                    │                    │
              ▼                    ▼                    ▼
       ┌──────────┐        ┌──────────────┐    ┌──────────────┐
       │ Provider │        │     Tool     │    │  Structured  │
       │  (core)  │        │  (optional)  │    │    Output    │
       └────┬─────┘        └──────┬───────┘    │  (optional)  │
            │                     │            └──────┬───────┘
            │                     │                   │
            ▼                     │                   │
       ┌──────────┐               │                   │
       │   Chat   │◄──────────────┴───────────────────┘
       │  (core)  │
       └────┬─────┘
            │
    ┌───────┴───────┬─────────────┬─────────────┐
    │               │             │             │
    ▼               ▼             ▼             ▼
┌────────┐   ┌──────────┐  ┌───────────┐  ┌──────────┐
│  MCP   │   │  Agent   │  │ Guardrail │  │  Prompt  │
└────────┘   └────┬─────┘  └───────────┘  └──────────┘
                  │
                  ▼
            ┌──────────┐
            │ Workflow │
            └──────────┘

Cross-cutting (can wrap any plugin):
├── Resilience (decorates Provider)
├── Cache (decorates Chat)
├── Context (decorates Chat/Agent)
└── Usage (observes all)
```

---

## Configuration Reference

Complete configuration file structure:

```php
// config/cortex.php
return [
    /*
    |--------------------------------------------------------------------------
    | Plugin Configuration
    |--------------------------------------------------------------------------
    */
    'plugins' => [
        'enabled' => [
            // Core (always loaded)
            'schema',
            'provider',
            'chat',
            
            // Optional
            'tool',
            'structured-output',
            'mcp',
            'agent',
            'workflow',
            'guardrail',
            'resilience',
            'prompt',
            'usage',
            'cache',
            'context',
        ],
        'disabled' => [],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy
    |--------------------------------------------------------------------------
    */
    'tenancy' => [
        'enabled' => true,
        'resolver' => \App\Tenancy\TenantResolver::class,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Events
    |--------------------------------------------------------------------------
    */
    'events' => [
        'enabled' => true,
        'disabled' => [],
        'logging' => [
            'enabled' => true,
            'channel' => 'cortex',
            'level' => 'debug',
            'events' => [], // Empty = all events
        ],
        'opentelemetry' => [
            'enabled' => env('CORTEX_OTEL_ENABLED', false),
            'service_name' => 'cortex',
            'traces' => true,
            'metrics' => true,
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Provider Configuration
    |--------------------------------------------------------------------------
    */
    'provider' => [
        'default' => 'bedrock',
        'providers' => [
            'bedrock' => [
                'driver' => 'bedrock',
                'region' => env('AWS_REGION', 'us-east-1'),
                'default_model' => 'anthropic.claude-3-5-sonnet-20241022-v2:0',
            ],
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Chat Configuration
    |--------------------------------------------------------------------------
    */
    'chat' => [
        'default_model' => null,
        'default_options' => [
            'temperature' => 0.7,
            'max_tokens' => 4096,
        ],
        'broadcasting' => [
            'driver' => 'echo',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Tool Configuration
    |--------------------------------------------------------------------------
    */
    'tool' => [
        'discovery' => [
            'enabled' => true,
            'paths' => [app_path('Tools')],
        ],
        'defaults' => [
            'timeout' => 30,
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Agent Configuration
    |--------------------------------------------------------------------------
    */
    'agent' => [
        'discovery' => [
            'enabled' => true,
            'paths' => [app_path('Agents')],
        ],
        'defaults' => [
            'max_iterations' => 10,
            'loop_strategy' => 'react',
        ],
        'async' => [
            'queue' => 'agents',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Workflow Configuration
    |--------------------------------------------------------------------------
    */
    'workflow' => [
        'discovery' => [
            'enabled' => true,
            'paths' => [app_path('Workflows')],
        ],
        'persistence' => [
            'driver' => 'database',
            'table' => 'cortex_workflow_states',
        ],
        'async' => [
            'queue' => 'workflows',
            'timeout' => 3600,
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Resilience Configuration
    |--------------------------------------------------------------------------
    */
    'resilience' => [
        'enabled' => true,
        'default' => [
            'retry' => ['attempts' => 3, 'delay' => 1000, 'multiplier' => 2.0],
            'timeout' => 30000,
        ],
        'providers' => [],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Usage Tracking Configuration
    |--------------------------------------------------------------------------
    */
    'usage' => [
        'enabled' => true,
        'tracking' => [
            'driver' => 'database',
            'table' => 'cortex_usage',
        ],
        'budgets' => [],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => false,
        'strategy' => 'exact',
        'store' => 'redis',
        'ttl' => 3600,
    ],
];
```

---

## Directory Structure

```
packages/cortex/
├── config/
│   └── cortex.php
├── database/
│   └── migrations/
│       ├── create_cortex_usage_table.php
│       └── create_cortex_workflow_states_table.php
├── docs/
│   ├── getting-started.md
│   ├── plugins/
│   │   ├── provider.md
│   │   ├── schema.md
│   │   ├── chat.md
│   │   ├── tool.md
│   │   ├── agent.md
│   │   ├── workflow.md
│   │   └── ...
│   └── extending.md
├── src/
│   ├── Cortex.php                    # Main facade
│   ├── CortexServiceProvider.php
│   ├── Contracts/
│   │   ├── PluginContract.php
│   │   ├── PluginManagerContract.php
│   │   └── ...
│   ├── Support/
│   │   ├── PluginManager.php
│   │   ├── ExtensionPoint.php
│   │   └── ...
│   ├── Plugins/
│   │   ├── Schema/
│   │   │   ├── SchemaPlugin.php
│   │   │   ├── Schema.php
│   │   │   ├── Types/
│   │   │   └── ...
│   │   ├── Provider/
│   │   │   ├── ProviderPlugin.php
│   │   │   ├── Contracts/
│   │   │   ├── Providers/
│   │   │   │   ├── BedrockProvider.php
│   │   │   │   └── FakeProvider.php
│   │   │   └── ...
│   │   ├── Chat/
│   │   │   ├── ChatPlugin.php
│   │   │   ├── Contracts/
│   │   │   ├── Messages/
│   │   │   ├── Broadcasting/
│   │   │   └── ...
│   │   ├── Tool/
│   │   ├── StructuredOutput/
│   │   ├── Mcp/
│   │   ├── Agent/
│   │   ├── Workflow/
│   │   ├── Guardrail/
│   │   ├── Resilience/
│   │   ├── Prompt/
│   │   ├── Usage/
│   │   ├── Cache/
│   │   └── Context/
│   ├── Events/
│   │   ├── CortexEvent.php
│   │   ├── Provider/
│   │   ├── Chat/
│   │   └── ...
│   └── Exceptions/
│       ├── CortexException.php
│       ├── ProviderException.php
│       └── ...
├── tests/
│   ├── Pest.php
│   ├── TestCase.php
│   ├── Unit/
│   │   ├── Schema/
│   │   ├── Provider/
│   │   └── ...
│   ├── Feature/
│   │   ├── ChatTest.php
│   │   ├── AgentTest.php
│   │   └── ...
│   └── Integration/
│       └── ...
├── composer.json
├── phpunit.xml
├── pint.json
└── README.md
```

---

## Testing Strategy

### Test Categories

1. **Unit Tests** - Test individual classes in isolation
2. **Feature Tests** - Test plugin functionality with mocked providers
3. **Integration Tests** - Test actual provider integration (optional, CI-gated)

### FakeProvider Usage

```php
// tests/Feature/ChatTest.php
use Cortex\Plugins\Provider\Providers\FakeProvider;

it('sends messages to the provider', function () {
    $fake = FakeProvider::fake([
        ChatResponse::make('Hello! How can I help?'),
    ]);
    
    Cortex::provider()->swap('bedrock', $fake);
    
    $response = Cortex::chat()->send(
        ChatRequest::make()->message('Hello')->build()
    );
    
    expect($response->content())->toBe('Hello! How can I help?');
    
    $fake->assertSentCount(1);
});

it('handles tool calls correctly', function () {
    $fake = FakeProvider::fake([
        ChatResponse::make()->withToolCall('get_weather', ['location' => 'NYC']),
        ChatResponse::make('The weather in NYC is sunny.'),
    ]);
    
    Cortex::provider()->swap('bedrock', $fake);
    
    $agent = Agent::make('test')
        ->tools(['get_weather'])
        ->build();
    
    $response = $agent->run('What is the weather in NYC?');
    
    expect($response->iterations)->toBe(2);
    expect($response->toolCalls)->toHaveCount(1);
});
```

### Testing Agents

```php
it('respects max iterations', function () {
    $fake = FakeProvider::fake([
        // Always request a tool call (infinite loop scenario)
        ChatResponse::make()->withToolCall('search', ['q' => 'test']),
        ChatResponse::make()->withToolCall('search', ['q' => 'test2']),
        ChatResponse::make()->withToolCall('search', ['q' => 'test3']),
        ChatResponse::make()->withToolCall('search', ['q' => 'test4']),
        ChatResponse::make()->withToolCall('search', ['q' => 'test5']),
    ]);
    
    Cortex::provider()->swap('bedrock', $fake);
    
    $agent = Agent::make('test')
        ->maxIterations(3)
        ->tools(['search'])
        ->build();
    
    $response = $agent->run('Find something');
    
    expect($response->iterations)->toBe(3);
    expect($response->reachedMaxIterations())->toBeTrue();
});
```

### Testing Workflows

```php
it('executes workflow nodes in order', function () {
    $executed = [];
    
    $workflow = WorkflowBuilder::make('test')
        ->callback('node1', function ($input, $state) use (&$executed) {
            $executed[] = 'node1';
            return NodeResult::make(['step' => 1]);
        })
        ->callback('node2', function ($input, $state) use (&$executed) {
            $executed[] = 'node2';
            return NodeResult::make(['step' => 2]);
        })
        ->startAt('node1')
        ->edge('node1', 'node2')
        ->endAt('node2')
        ->build();
    
    $result = $workflow->run([]);
    
    expect($executed)->toBe(['node1', 'node2']);
    expect($result->status)->toBe(WorkflowStatus::Completed);
});
```

---

## Quick Start Example

```php
// 1. Basic chat
$response = Cortex::chat()->send(
    ChatRequest::make()
        ->system('You are a helpful assistant.')
        ->message('What is Laravel?')
        ->build()
);

echo $response->content();

// 2. Streaming
foreach (Cortex::chat()->stream($request)->text() as $chunk) {
    echo $chunk;
}

// 3. With tools
$response = Cortex::chat()->send(
    ChatRequest::make()
        ->message('What is the weather in London?')
        ->tools(['get_weather'])
        ->build()
);

// 4. Using an agent
$agent = Cortex::agent('research-assistant');
$response = $agent->run('Research the latest trends in AI');

// 5. Running a workflow
$workflow = Cortex::workflow('content-pipeline');
$result = $workflow->run([
    'topic' => 'Laravel 12 Features',
    'style' => 'blog post',
]);
```

---

## Next Steps

1. **Phase 1: Core Foundation**
   - Plugin system implementation
   - Schema plugin
   - Provider plugin with Bedrock
   - FakeProvider for testing
   
2. **Phase 2: Chat & Tools**
   - Chat plugin with streaming
   - Broadcasting (Echo + SSE)
   - Tool plugin
   - Structured output plugin

3. **Phase 3: Intelligence**
   - Agent plugin with loops
   - Memory strategies
   - MCP integration
   
4. **Phase 4: Orchestration**
   - Workflow plugin
   - Async execution
   - State persistence

5. **Phase 5: Production Readiness**
   - Resilience plugin
   - Usage tracking
   - Guardrails
   - Caching
   - Context management
