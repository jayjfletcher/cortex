# Provider Plugin

The Provider plugin manages LLM provider connections and provides an abstraction layer for interacting with different AI services.

## Overview

- **Plugin ID:** `provider`
- **Dependencies:** `schema`
- **Provides:** `providers`

## Supported Providers

### AWS Bedrock

The default provider supports AWS Bedrock's Converse API:

```php
// config/cortex.php
'provider' => [
    'default' => 'bedrock',
    'providers' => [
        'bedrock' => [
            'driver' => 'bedrock',
            'region' => env('AWS_REGION', 'us-east-1'),
            'version' => 'latest',
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
            'default_model' => 'anthropic.claude-3-5-sonnet-20241022-v2:0',
        ],
    ],
],
```

**Supported Bedrock Models:**
- Claude 3.5 Sonnet (`anthropic.claude-3-5-sonnet-20241022-v2:0`)
- Claude 3.5 Haiku (`anthropic.claude-3-5-haiku-20241022-v1:0`)
- Claude 3 Opus (`anthropic.claude-3-opus-20240229-v1:0`)
- Claude 3 Sonnet (`anthropic.claude-3-sonnet-20240229-v1:0`)
- Claude 3 Haiku (`anthropic.claude-3-haiku-20240307-v1:0`)

## Provider Contract

All providers implement `ProviderContract`:

```php
interface ProviderContract
{
    public function id(): string;
    public function name(): string;
    public function capabilities(): ProviderCapabilities;
    public function models(): Collection;
    public function model(string $id): Model;
    public function countTokens(string|array|Message $content, ?string $model = null): int;
    public function chat(ChatRequest $request): ChatResponse;
    public function stream(ChatRequest $request): StreamedResponse;
    public function supports(string $feature): bool;
    public function withOptions(array $options): static;
    public function defaultModel(): string;
}
```

## Provider Registry

Access providers through the registry:

```php
use JayI\Cortex\Plugins\Provider\Contracts\ProviderRegistryContract;
use JayI\Cortex\Plugins\Provider\ProviderCollection;

$registry = app(ProviderRegistryContract::class);

// Get the default provider
$provider = $registry->default();

// Get a specific provider
$provider = $registry->get('bedrock');

// Check if provider exists
$registry->has('bedrock'); // true

// List all providers (returns ProviderCollection)
$providers = $registry->all();

// Get specific providers (returns ProviderCollection)
$subset = $registry->only(['bedrock', 'openai']);

// Get all except specified (returns ProviderCollection)
$filtered = $registry->except(['deprecated-provider']);
```

## Provider Capabilities

Each provider declares its capabilities:

```php
$capabilities = $provider->capabilities();

// Check capabilities
$capabilities->streaming;       // bool - Supports streaming
$capabilities->tools;           // bool - Supports tool calling
$capabilities->parallelTools;   // bool - Supports parallel tool execution
$capabilities->vision;          // bool - Supports image input
$capabilities->audio;           // bool - Supports audio input
$capabilities->documents;       // bool - Supports document input
$capabilities->structuredOutput;// bool - Native structured output
$capabilities->jsonMode;        // bool - JSON mode support
$capabilities->systemMessages;  // bool - Supports system messages
$capabilities->maxContextWindow;// int - Maximum context size
$capabilities->maxOutputTokens; // int - Maximum output tokens

// Check specific feature
$provider->supports('streaming'); // bool
```

## Model Information

Get information about available models:

```php
// List all models
$models = $provider->models();

foreach ($models as $model) {
    echo $model->id;           // "anthropic.claude-3-5-sonnet-20241022-v2:0"
    echo $model->name;         // "Claude 3.5 Sonnet"
    echo $model->contextWindow;// 200000
    echo $model->maxOutputTokens; // 8192
}

// Get specific model
$model = $provider->model('anthropic.claude-3-5-sonnet-20241022-v2:0');

// Estimate cost
$cost = $model->estimateCost(
    inputTokens: 1000,
    outputTokens: 500
);
```

## Direct Provider Usage

While you'll typically use the Chat plugin, you can call providers directly:

```php
use JayI\Cortex\Plugins\Chat\ChatRequest;
use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;

$provider = $registry->get('bedrock');

$request = new ChatRequest(
    messages: MessageCollection::make()->user('Hello!'),
    model: 'anthropic.claude-3-5-sonnet-20241022-v2:0',
);

// Synchronous
$response = $provider->chat($request);
echo $response->content();

// Streaming
$stream = $provider->stream($request);
foreach ($stream->text() as $chunk) {
    echo $chunk;
}
```

## Provider Options

Pass provider-specific options:

```php
$provider = $registry->get('bedrock')
    ->withOptions([
        'guardrail_identifier' => 'my-guardrail',
        'guardrail_version' => 'DRAFT',
    ]);
```

## Token Counting

Estimate token usage:

```php
// Count tokens for text
$tokens = $provider->countTokens('Hello, how are you?');

// Count tokens for a message
$tokens = $provider->countTokens(Message::user('Hello!'));
```

## FakeProvider for Testing

Use `FakeProvider` in tests to avoid API calls:

```php
use JayI\Cortex\Plugins\Provider\Providers\FakeProvider;

// Create with queued responses
$fake = FakeProvider::fake([
    'First response',
    'Second response',
]);

// Create with static text
$fake = FakeProvider::text('Always returns this');

// Create with tool calls
$fake = FakeProvider::withToolCalls([
    ['name' => 'get_weather', 'input' => ['location' => 'Paris']],
]);

// Use response factory for dynamic responses
$fake = FakeProvider::fake()->respondWith(
    fn (ChatRequest $request) => 'Echo: ' . $request->messages->last()->text()
);

// Make requests
$response = $fake->chat($request);

// Assertions
$fake->assertSentCount(1);
$fake->assertNothingSent();
$fake->assertSent(fn ($r) => str_contains($r->messages->last()->text(), 'hello'));

// Access recorded requests
$requests = $fake->recordedRequests();

// Reset state
$fake->reset();
```

### Queued Responses

```php
$fake = FakeProvider::fake([
    'Response 1',
    'Response 2',
    ChatResponse::fromText('Custom response'),
    fn () => ChatResponse::fromText('Dynamic'),
]);

// Each chat() call consumes next response
$response1 = $fake->chat($request); // "Response 1"
$response2 = $fake->chat($request); // "Response 2"
```

### Custom ChatResponse Objects

```php
use JayI\Cortex\Plugins\Chat\ChatResponse;
use JayI\Cortex\Plugins\Chat\Usage;
use JayI\Cortex\Plugins\Chat\StopReason;
use JayI\Cortex\Plugins\Chat\Messages\Message;

$fake = FakeProvider::fake([
    new ChatResponse(
        message: Message::assistant('Custom response'),
        usage: new Usage(inputTokens: 100, outputTokens: 50),
        stopReason: StopReason::EndTurn,
        model: 'test-model',
    ),
]);
```

### Streaming with FakeProvider

```php
$fake = FakeProvider::text('Hello World');

$stream = $fake->stream($request);

foreach ($stream->text() as $chunk) {
    echo $chunk; // Chunks of "Hello World"
}
```

## Error Handling

Provider errors throw `ProviderException`:

```php
use JayI\Cortex\Exceptions\ProviderException;

try {
    $response = $provider->chat($request);
} catch (ProviderException $e) {
    $context = $e->context();
    // Handle error
}
```

Common exceptions:

```php
// Provider not found
ProviderException::notFound($providerId);

// Provider not configured
ProviderException::notConfigured($providerId);

// API error
ProviderException::apiError($provider, $message, $previous);

// Rate limited
ProviderException::rateLimited($provider, $retryAfter);
```

## Creating a Custom Provider

Implement `ProviderContract`:

```php
use JayI\Cortex\Plugins\Provider\Contracts\ProviderContract;

class OpenAIProvider implements ProviderContract
{
    public function __construct(
        protected array $config,
    ) {}

    public function id(): string
    {
        return 'openai';
    }

    public function name(): string
    {
        return 'OpenAI';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            streaming: true,
            tools: true,
            vision: true,
            structuredOutput: true,
            // ...
        );
    }

    public function chat(ChatRequest $request): ChatResponse
    {
        // Implement API call
    }

    // ... implement other methods
}
```

Register via extension point:

```php
public function boot(PluginManagerContract $manager): void
{
    $manager->extend('providers', new OpenAIProvider($this->config));
}
```

## Configuration Reference

```php
// config/cortex.php
'provider' => [
    // Default provider to use
    'default' => 'bedrock',

    // Provider configurations
    'providers' => [
        'bedrock' => [
            'driver' => 'bedrock',
            'region' => env('AWS_REGION', 'us-east-1'),
            'version' => 'latest',
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
            'default_model' => env('CORTEX_DEFAULT_MODEL', 'anthropic.claude-3-5-sonnet-20241022-v2:0'),
            'models' => [
                // Custom model definitions
            ],
        ],
    ],
],
```
