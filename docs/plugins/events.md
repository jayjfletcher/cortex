# Event System

Cortex provides a comprehensive event system for observability and extensibility. Events are dispatched throughout the system at key points.

## Overview

- **Plugin ID:** `events`
- **Dependencies:** None
- **Provides:** Event dispatching infrastructure

## Event Categories

| Category | Events |
|----------|--------|
| Provider | `ProviderRegistered`, `BeforeProviderRequest`, `AfterProviderResponse`, `ProviderError`, `ProviderRateLimited` |
| Chat | `BeforeChatSend`, `AfterChatReceive`, `ChatStreamStarted`, `ChatStreamChunk`, `ChatStreamCompleted`, `ChatError` |
| Tool | `ToolRegistered`, `BeforeToolExecution`, `AfterToolExecution`, `ToolError` |
| Agent | `AgentRunStarted`, `AgentIterationStarted`, `AgentIterationCompleted`, `AgentToolCalled`, `AgentRunCompleted`, `AgentRunFailed`, `AgentMaxIterationsReached` |
| Workflow | `WorkflowStarted`, `WorkflowNodeEntered`, `WorkflowNodeExited`, `WorkflowBranchTaken`, `WorkflowPaused`, `WorkflowResumed`, `WorkflowCompleted`, `WorkflowFailed` |
| Guardrail | `GuardrailRegistered`, `GuardrailChecked`, `GuardrailBlocked` |

## Base Event Class

All Cortex events extend `CortexEvent`:

```php
use JayI\Cortex\Events\CortexEvent;

class CortexEvent
{
    public readonly DateTimeImmutable $timestamp;
    public readonly ?string $tenantId;
    public readonly ?string $correlationId;
    public readonly array $metadata;
}
```

## Listening to Events

```php
use JayI\Cortex\Events\Chat\BeforeChatSend;
use JayI\Cortex\Events\Chat\AfterChatReceive;
use JayI\Cortex\Events\Agent\AgentRunCompleted;

// In EventServiceProvider
protected $listen = [
    BeforeChatSend::class => [
        LogChatRequest::class,
    ],
    AfterChatReceive::class => [
        TrackTokenUsage::class,
    ],
    AgentRunCompleted::class => [
        NotifyAgentComplete::class,
    ],
];
```

## Event Subscriber

Cortex includes a logging subscriber:

```php
use JayI\Cortex\Events\Subscribers\EventLoggingSubscriber;

// Automatically logs all Cortex events
Event::subscribe(EventLoggingSubscriber::class);
```

## Disabling Events

```php
// config/cortex.php
'events' => [
    'enabled' => true,  // Disable all events
    'disabled' => [     // Disable specific events
        \JayI\Cortex\Events\Chat\ChatStreamChunk::class,
    ],
],
```

## Provider Events

### ProviderRegistered

Dispatched when a provider is registered:

```php
use JayI\Cortex\Events\Provider\ProviderRegistered;

Event::listen(ProviderRegistered::class, function ($event) {
    Log::info('Provider registered', [
        'provider_id' => $event->providerId,
    ]);
});
```

### BeforeProviderRequest / AfterProviderResponse

```php
use JayI\Cortex\Events\Provider\BeforeProviderRequest;
use JayI\Cortex\Events\Provider\AfterProviderResponse;
use JayI\Cortex\Events\Provider\ProviderError;

class ProviderMonitor
{
    public function handleBeforeRequest(BeforeProviderRequest $event): void
    {
        Log::info('Provider request', [
            'provider' => $event->provider,
            'model' => $event->model,
        ]);
    }

    public function handleAfterResponse(AfterProviderResponse $event): void
    {
        Log::info('Provider response', [
            'provider' => $event->provider,
            'duration' => $event->duration,
            'tokens' => $event->response->usage->totalTokens(),
        ]);
    }

    public function handleError(ProviderError $event): void
    {
        Log::error('Provider error', [
            'provider' => $event->provider,
            'error' => $event->exception->getMessage(),
        ]);
    }
}
```

### ProviderRateLimited

Dispatched when a provider rate limit is hit:

```php
use JayI\Cortex\Events\Provider\ProviderRateLimited;

Event::listen(ProviderRateLimited::class, function ($event) {
    Log::warning('Rate limited', [
        'provider' => $event->provider,
        'retry_after' => $event->retryAfter,
    ]);
});
```

## Chat Events

### BeforeChatSend / AfterChatReceive

```php
use JayI\Cortex\Events\Chat\BeforeChatSend;
use JayI\Cortex\Events\Chat\AfterChatReceive;

Event::listen(BeforeChatSend::class, function ($event) {
    Log::debug('Sending chat request', [
        'model' => $event->request->model,
        'message_count' => count($event->request->messages),
    ]);
});

Event::listen(AfterChatReceive::class, function ($event) {
    Log::debug('Received chat response', [
        'tokens' => $event->response->usage->totalTokens(),
    ]);
});
```

### Streaming Events

```php
use JayI\Cortex\Events\Chat\ChatStreamStarted;
use JayI\Cortex\Events\Chat\ChatStreamChunk;
use JayI\Cortex\Events\Chat\ChatStreamCompleted;

Event::listen(ChatStreamStarted::class, function ($event) {
    // Stream beginning
});

Event::listen(ChatStreamChunk::class, function ($event) {
    // Each chunk received
    echo $event->chunk->delta;
});

Event::listen(ChatStreamCompleted::class, function ($event) {
    // Stream finished
});
```

## Tool Events

```php
use JayI\Cortex\Events\Tool\ToolRegistered;
use JayI\Cortex\Events\Tool\BeforeToolExecution;
use JayI\Cortex\Events\Tool\AfterToolExecution;
use JayI\Cortex\Events\Tool\ToolError;

Event::listen(BeforeToolExecution::class, function ($event) {
    Log::info('Executing tool', [
        'tool' => $event->tool->id(),
        'input' => $event->input,
    ]);
});

Event::listen(AfterToolExecution::class, function ($event) {
    Log::info('Tool completed', [
        'tool' => $event->tool->id(),
        'duration' => $event->duration,
    ]);
});
```

## Agent Events

```php
use JayI\Cortex\Events\Agent\AgentRunStarted;
use JayI\Cortex\Events\Agent\AgentIterationStarted;
use JayI\Cortex\Events\Agent\AgentIterationCompleted;
use JayI\Cortex\Events\Agent\AgentToolCalled;
use JayI\Cortex\Events\Agent\AgentRunCompleted;
use JayI\Cortex\Events\Agent\AgentRunFailed;

Event::listen(AgentRunStarted::class, function ($event) {
    Log::info('Agent run started', [
        'agent' => $event->agent->id(),
        'run_id' => $event->runId,
    ]);
});

Event::listen(AgentIterationCompleted::class, function ($event) {
    Log::debug('Agent iteration', [
        'iteration' => $event->iteration,
        'has_tool_calls' => $event->hasToolCalls,
    ]);
});

Event::listen(AgentRunCompleted::class, function ($event) {
    Log::info('Agent run completed', [
        'agent' => $event->agent->id(),
        'iterations' => $event->response->iterationCount,
        'tokens' => $event->response->totalUsage->totalTokens(),
    ]);
});
```

## Workflow Events

```php
use JayI\Cortex\Events\Workflow\WorkflowStarted;
use JayI\Cortex\Events\Workflow\WorkflowNodeEntered;
use JayI\Cortex\Events\Workflow\WorkflowNodeExited;
use JayI\Cortex\Events\Workflow\WorkflowCompleted;

Event::listen(WorkflowStarted::class, function ($event) {
    Log::info('Workflow started', [
        'workflow' => $event->workflow->id(),
        'run_id' => $event->runId,
    ]);
});

Event::listen(WorkflowNodeExited::class, function ($event) {
    Log::debug('Node completed', [
        'node' => $event->nodeId,
        'success' => $event->result->isSuccess(),
    ]);
});
```

## Guardrail Events

```php
use JayI\Cortex\Events\Guardrail\GuardrailChecked;
use JayI\Cortex\Events\Guardrail\GuardrailBlocked;

Event::listen(GuardrailBlocked::class, function ($event) {
    Log::warning('Content blocked by guardrail', [
        'guardrail' => $event->guardrail->id(),
        'reason' => $event->result->message,
    ]);
});
```

## Configuration

```php
// config/cortex.php
'events' => [
    'enabled' => true,
    'disabled' => [],
    'log_channel' => 'cortex',
],
```
