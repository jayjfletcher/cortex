# Chat Plugin

The Chat plugin provides the core chat completion functionality, including synchronous requests, streaming responses, and broadcasting support.

## Overview

- **Plugin ID:** `chat`
- **Dependencies:** `schema`, `provider`
- **Provides:** `chat`

## Chat Client

The `ChatClient` is the main interface for sending chat requests:

```php
use JayI\Cortex\Plugins\Chat\Contracts\ChatClientContract;

$client = app(ChatClientContract::class);

// Send a synchronous request
$response = $client->send($request);

// Stream a response
$stream = $client->stream($request);

// Broadcast to a channel
$response = $client->broadcast('channel-id', $request);

// Get an SSE response
$sseResponse = $client->sse($request);
```

### Using a Specific Provider

```php
$client = app(ChatClientContract::class);

// By provider name
$response = $client->using('bedrock')->send($request);

// By provider instance
$response = $client->using($customProvider)->send($request);
```

## Building Requests

Use the `ChatRequestBuilder` for a fluent API:

```php
use JayI\Cortex\Plugins\Chat\ChatRequestBuilder;

$builder = new ChatRequestBuilder();

$response = $builder
    ->system('You are a helpful assistant.')
    ->message('Hello, how are you?')
    ->model('anthropic.claude-3-5-sonnet-20241022-v2:0')
    ->temperature(0.7)
    ->maxTokens(1024)
    ->send();
```

### Builder Methods

```php
$builder
    // Set system prompt
    ->system(string $prompt)

    // Add a single message (string becomes user message)
    ->message(string|Message $message)

    // Set all messages
    ->messages(array|MessageCollection $messages)

    // Set the model
    ->model(string $model)

    // Set options
    ->options(ChatOptions|array $options)

    // Set temperature (0.0 - 1.0)
    ->temperature(float $temperature)

    // Set max output tokens
    ->maxTokens(int $maxTokens)

    // Add tools
    ->withTools(ToolCollection|array $tools)

    // Set response schema (for structured output)
    ->responseSchema(Schema $schema)

    // Add metadata
    ->metadata(array $metadata)

    // Build without sending
    ->build(): ChatRequest

    // Build and send
    ->send(): ChatResponse

    // Build and stream
    ->stream(): StreamedResponse
```

## Messages

### Creating Messages

```php
use JayI\Cortex\Plugins\Chat\Messages\Message;

// System message
$system = Message::system('You are a helpful assistant.');

// User message (text)
$user = Message::user('Hello!');

// Assistant message
$assistant = Message::assistant('Hi there!');

// Tool result message
$result = Message::toolResult('tool-use-id-123', ['data' => 'value']);
```

### Message Content Types

Messages can contain multiple content types:

```php
use JayI\Cortex\Plugins\Chat\Messages\TextContent;
use JayI\Cortex\Plugins\Chat\Messages\ImageContent;
use JayI\Cortex\Plugins\Chat\Messages\DocumentContent;
use JayI\Cortex\Plugins\Chat\Messages\ToolUseContent;
use JayI\Cortex\Plugins\Chat\Messages\ToolResultContent;

// Text content
$text = new TextContent('Hello, world!');

// Image content (base64)
$image = new ImageContent(
    data: base64_encode($imageData),
    mediaType: 'image/jpeg',
    sourceType: SourceType::Base64
);

// Image content (URL)
$image = new ImageContent(
    data: 'https://example.com/image.jpg',
    mediaType: 'image/jpeg',
    sourceType: SourceType::Url
);

// Document content
$document = new DocumentContent(
    data: base64_encode($pdfData),
    mediaType: 'application/pdf',
    name: 'document.pdf'
);

// Multi-content message
$message = Message::user([
    new TextContent('What is in this image?'),
    new ImageContent($imageData, 'image/png', SourceType::Base64),
]);
```

### Extracting Message Content

```php
$message = Message::assistant('Hello!');

// Get text content
$text = $message->text(); // "Hello!"

// Get images
$images = $message->images(); // ImageContent[]

// Get tool calls
$toolCalls = $message->toolCalls(); // ToolUseContent[]

// Check for tool calls
if ($message->hasToolCalls()) {
    foreach ($message->toolCalls() as $toolCall) {
        echo $toolCall->name;
        print_r($toolCall->input);
    }
}
```

## Message Collections

```php
use JayI\Cortex\Plugins\Chat\Messages\MessageCollection;

$messages = MessageCollection::make()
    ->system('You are helpful.')
    ->user('Hello!')
    ->assistant('Hi there!')
    ->user('How are you?');

// Get first/last message
$first = $messages->first();
$last = $messages->last();

// Filter by role
$userMessages = $messages->byRole(MessageRole::User);
$withoutSystem = $messages->withoutSystem();

// Count
$count = $messages->count();
$messages->isEmpty();
$messages->isNotEmpty();

// Iterate
foreach ($messages as $message) {
    echo $message->text();
}

// Token management
$tokens = $messages->estimateTokens($provider);
$truncated = $messages->truncateToTokens(4000, $provider);
```

## Chat Response

```php
use JayI\Cortex\Plugins\Chat\ChatResponse;

$response = $client->send($request);

// Get text content
echo $response->content();

// Get the full message
$message = $response->message;

// Check stop reason
$response->isComplete();      // Natural end
$response->isTruncated();     // Hit max tokens
$response->requiresToolExecution(); // Tool call pending

// Access usage information
$response->usage->inputTokens;
$response->usage->outputTokens;
$response->usage->totalTokens();

// Get tool calls
if ($response->hasToolCalls()) {
    $toolCalls = $response->toolCalls();
    $firstTool = $response->firstToolCall();
}
```

### Stop Reasons

```php
use JayI\Cortex\Plugins\Chat\StopReason;

switch ($response->stopReason) {
    case StopReason::EndTurn:
        // Model finished naturally
        break;
    case StopReason::MaxTokens:
        // Hit token limit
        break;
    case StopReason::StopSequence:
        // Hit a stop sequence
        break;
    case StopReason::ToolUse:
        // Model wants to use a tool
        break;
}
```

## Streaming

### Basic Streaming

```php
$stream = $client->stream($request);

// Iterate over chunks
foreach ($stream as $chunk) {
    if ($chunk->isText()) {
        echo $chunk->text;
    }
}

// Or just get text chunks
foreach ($stream->text() as $text) {
    echo $text;
}

// Collect into final response
$response = $stream->collect();
```

### Stream with Callback

```php
$response = $stream->each(function ($chunk, $index) {
    if ($chunk->isText()) {
        echo $chunk->text;
        flush();
    }
});
```

### Stream Chunks

```php
use JayI\Cortex\Plugins\Chat\StreamChunk;
use JayI\Cortex\Plugins\Chat\StreamChunkType;

foreach ($stream as $chunk) {
    switch ($chunk->type) {
        case StreamChunkType::Text:
            echo $chunk->text;
            break;
        case StreamChunkType::ToolUse:
            // Tool call started
            $toolName = $chunk->toolUse->name;
            break;
        case StreamChunkType::ToolUseInput:
            // Streaming tool input
            break;
        case StreamChunkType::ContentBlockStop:
            // Content block finished
            break;
        case StreamChunkType::MessageEnd:
            // Full response done
            $usage = $chunk->usage;
            break;
    }
}
```

## Broadcasting

### Echo Broadcaster

Broadcast streaming responses over Laravel Echo:

```php
$client = app(ChatClientContract::class);

// Returns the final ChatResponse after broadcasting is complete
$response = $client->broadcast('conversation.123', $request);
```

The broadcaster dispatches events:

- `ChatStreamChunkEvent` - For each chunk
- `ChatStreamCompleteEvent` - When streaming completes

### SSE Responses

Get a Server-Sent Events response for direct HTTP streaming:

```php
// In a controller
public function stream(Request $request)
{
    $chatRequest = (new ChatRequestBuilder())
        ->message($request->input('message'))
        ->build();

    return app(ChatClientContract::class)->sse($chatRequest);
}
```

## Chat Options

```php
use JayI\Cortex\Plugins\Chat\ChatOptions;

$options = new ChatOptions(
    temperature: 0.7,        // Randomness (0.0 - 1.0)
    maxTokens: 4096,         // Maximum output tokens
    topP: 0.9,               // Nucleus sampling
    topK: 40,                // Top-k sampling
    stopSequences: ['END'],  // Stop generation sequences
    toolChoice: 'auto',      // Tool selection mode
    providerOptions: [       // Provider-specific options
        'guardrail_identifier' => 'my-guardrail',
    ],
);

// Get defaults
$defaults = ChatOptions::defaults();

// Merge options
$merged = $defaults->merge($options);
```

## Token Usage

```php
use JayI\Cortex\Plugins\Chat\Usage;

$response = $client->send($request);

$usage = $response->usage;
$usage->inputTokens;   // Tokens in request
$usage->outputTokens;  // Tokens in response
$usage->totalTokens(); // Sum of both

// Add usage from multiple requests
$total = $usage1->add($usage2);

// Zero usage (for testing)
$zero = Usage::zero();
```

## Hooks

The Chat plugin provides hooks for request/response modification:

```php
use JayI\Cortex\Contracts\PluginManagerContract;

$manager = app(PluginManagerContract::class);

// Modify requests before sending
$manager->addHook('chat.before_send', function (ChatRequest $request) {
    // Add custom system prompt
    return $request;
}, priority: 10);

// Modify responses after receiving
$manager->addHook('chat.after_receive', function (ChatResponse $response, ChatRequest $request) {
    // Log or modify response
    return $response;
}, priority: 10);
```

## Configuration

```php
// config/cortex.php
'chat' => [
    'broadcasting' => [
        'driver' => 'echo', // 'echo' or custom

        'sse' => [
            'retry' => 3000, // SSE retry interval in ms
        ],
    ],
],
```

## Error Handling

```php
use JayI\Cortex\Exceptions\ChatException;

try {
    $response = $client->send($request);
} catch (ChatException $e) {
    $context = $e->context();
    // Handle error
}
```

### Plugin Dependency Exceptions

The `ChatRequestBuilder` requires specific plugins to be enabled for certain methods. A `PluginException` is thrown if the required plugin is not registered:

```php
use JayI\Cortex\Plugins\Chat\ChatRequestBuilder;
use JayI\Cortex\Exceptions\PluginException;

// Requires 'tool' plugin to be enabled
try {
    $builder = new ChatRequestBuilder();
    $builder->withTools($tools);  // Throws if tool plugin disabled
} catch (PluginException $e) {
    // "Plugin [tool] is disabled."
}

// Requires 'mcp' plugin to be enabled
try {
    $builder = new ChatRequestBuilder();
    $builder->withMcpServers($servers);  // Throws if mcp plugin disabled
} catch (PluginException $e) {
    // "Plugin [mcp] is disabled."
}
```

**Methods requiring `tool` plugin:**
- `withTools()`

**Methods requiring `mcp` plugin:**
- `withMcpServers()`
- `addMcpServer()`
