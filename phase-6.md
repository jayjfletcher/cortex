# Phase 6: Complete Missing Features

This phase completes the Cortex package by implementing features from plan-refined.md that were not implemented in previous phases.

---

## Overview

| Component | Status | Priority |
|-----------|--------|----------|
| Event System | Missing | High |
| Multi-Tenancy | Missing | High |
| Prompt Plugin | Missing | Medium |
| Agent Async Execution | Partial | Medium |
| Workflow Persistence | Partial | Medium |
| RAG Integration | Partial | Low |

---

## 1. Event System

### 1.1 Base Event Class

Create `src/Events/CortexEvent.php`:

```php
<?php

declare(strict_types=1);

namespace Cortex\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class CortexEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly float $timestamp;
    public readonly ?string $tenantId;
    public readonly ?string $correlationId;
    public readonly array $metadata;

    public function __construct(
        ?string $tenantId = null,
        ?string $correlationId = null,
        array $metadata = [],
    ) {
        $this->timestamp = microtime(true);
        $this->tenantId = $tenantId;
        $this->correlationId = $correlationId;
        $this->metadata = $metadata;
    }
}
```

### 1.2 Provider Events

Create `src/Events/Provider/` directory with:

| File | Class | Payload |
|------|-------|---------|
| `ProviderRegistered.php` | `ProviderRegistered` | `provider`, `capabilities` |
| `BeforeProviderRequest.php` | `BeforeProviderRequest` | `provider`, `request`, `model` |
| `AfterProviderResponse.php` | `AfterProviderResponse` | `provider`, `request`, `response`, `duration` |
| `ProviderError.php` | `ProviderError` | `provider`, `request`, `exception` |
| `ProviderRateLimited.php` | `ProviderRateLimited` | `provider`, `retryAfter` |

### 1.3 Chat Events

Create `src/Events/Chat/` directory with:

| File | Class | Payload |
|------|-------|---------|
| `BeforeChatSend.php` | `BeforeChatSend` | `request` |
| `AfterChatReceive.php` | `AfterChatReceive` | `request`, `response` |
| `ChatStreamStarted.php` | `ChatStreamStarted` | `request` |
| `ChatStreamChunk.php` | `ChatStreamChunk` | `chunk`, `index` |
| `ChatStreamCompleted.php` | `ChatStreamCompleted` | `request`, `fullResponse` |
| `ChatError.php` | `ChatError` | `request`, `exception` |

Note: Move existing `ChatStreamChunkEvent` and `ChatStreamCompleteEvent` from Broadcasting/ to Events/Chat/ and extend `CortexEvent`.

### 1.4 Tool Events

Create `src/Events/Tool/` directory with:

| File | Class | Payload |
|------|-------|---------|
| `ToolRegistered.php` | `ToolRegistered` | `tool` |
| `BeforeToolExecution.php` | `BeforeToolExecution` | `tool`, `input`, `context` |
| `AfterToolExecution.php` | `AfterToolExecution` | `tool`, `input`, `output`, `duration` |
| `ToolError.php` | `ToolError` | `tool`, `input`, `exception` |

### 1.5 Agent Events

Create `src/Events/Agent/` directory with:

| File | Class | Payload |
|------|-------|---------|
| `AgentRegistered.php` | `AgentRegistered` | `agent` |
| `AgentRunStarted.php` | `AgentRunStarted` | `agent`, `input` |
| `AgentIterationStarted.php` | `AgentIterationStarted` | `agent`, `iteration`, `state` |
| `AgentIterationCompleted.php` | `AgentIterationCompleted` | `agent`, `iteration`, `state`, `response` |
| `AgentToolCalled.php` | `AgentToolCalled` | `agent`, `tool`, `input` |
| `AgentRunCompleted.php` | `AgentRunCompleted` | `agent`, `input`, `output`, `iterations` |
| `AgentRunFailed.php` | `AgentRunFailed` | `agent`, `input`, `exception`, `iterations` |
| `AgentMaxIterationsReached.php` | `AgentMaxIterationsReached` | `agent`, `state` |

### 1.6 Workflow Events

Create `src/Events/Workflow/` directory with:

| File | Class | Payload |
|------|-------|---------|
| `WorkflowRegistered.php` | `WorkflowRegistered` | `workflow` |
| `WorkflowStarted.php` | `WorkflowStarted` | `workflow`, `input` |
| `WorkflowNodeEntered.php` | `WorkflowNodeEntered` | `workflow`, `node`, `state` |
| `WorkflowNodeExited.php` | `WorkflowNodeExited` | `workflow`, `node`, `state`, `output` |
| `WorkflowBranchTaken.php` | `WorkflowBranchTaken` | `workflow`, `node`, `branch` |
| `WorkflowPaused.php` | `WorkflowPaused` | `workflow`, `state`, `reason` |
| `WorkflowResumed.php` | `WorkflowResumed` | `workflow`, `state`, `input` |
| `WorkflowCompleted.php` | `WorkflowCompleted` | `workflow`, `input`, `output` |
| `WorkflowFailed.php` | `WorkflowFailed` | `workflow`, `state`, `exception` |

### 1.7 Guardrail Events

Create `src/Events/Guardrail/` directory with:

| File | Class | Payload |
|------|-------|---------|
| `GuardrailRegistered.php` | `GuardrailRegistered` | `guardrail` |
| `GuardrailChecked.php` | `GuardrailChecked` | `guardrail`, `content`, `result` |
| `GuardrailBlocked.php` | `GuardrailBlocked` | `guardrail`, `content`, `violations` |

### 1.8 Event Dispatcher Trait

Create `src/Events/Concerns/DispatchesCortexEvents.php`:

```php
<?php

declare(strict_types=1);

namespace Cortex\Events\Concerns;

use Cortex\Events\CortexEvent;
use Illuminate\Support\Facades\Config;

trait DispatchesCortexEvents
{
    protected function dispatchCortexEvent(CortexEvent $event): void
    {
        if (!Config::get('cortex.events.enabled', true)) {
            return;
        }

        $disabledEvents = Config::get('cortex.events.disabled', []);

        if (in_array(get_class($event), $disabledEvents, true)) {
            return;
        }

        event($event);
    }
}
```

### 1.9 Integration Points

Update the following classes to dispatch events:

1. **ProviderRegistry** - dispatch `ProviderRegistered`
2. **BedrockProvider** (and other providers) - dispatch `BeforeProviderRequest`, `AfterProviderResponse`, `ProviderError`, `ProviderRateLimited`
3. **ChatClient** - dispatch `BeforeChatSend`, `AfterChatReceive`, `ChatStreamStarted`, `ChatStreamChunk`, `ChatStreamCompleted`, `ChatError`
4. **ToolExecutor** - dispatch `ToolRegistered`, `BeforeToolExecution`, `AfterToolExecution`, `ToolError`
5. **SimpleAgentLoop** (and other loops) - dispatch all agent events
6. **WorkflowEngine** - dispatch all workflow events
7. **GuardrailPipeline** - dispatch all guardrail events

### 1.10 Configuration

Add to `config/cortex.php`:

```php
'events' => [
    'enabled' => true,

    // Disable specific high-frequency events
    'disabled' => [
        // \Cortex\Events\Chat\ChatStreamChunk::class,
    ],

    // Automatic event logging
    'logging' => [
        'enabled' => true,
        'channel' => 'cortex',
        'level' => 'debug',
        'events' => [
            // Empty = all events, or specify specific events to log
            \Cortex\Events\Provider\ProviderError::class,
            \Cortex\Events\Chat\ChatError::class,
            \Cortex\Events\Tool\ToolError::class,
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

### 1.11 Event Logging Subscriber

Create `src/Events/Subscribers/EventLoggingSubscriber.php`:

```php
<?php

declare(strict_types=1);

namespace Cortex\Events\Subscribers;

use Cortex\Events\CortexEvent;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class EventLoggingSubscriber
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen('Cortex\Events\*', [$this, 'handleEvent']);
    }

    public function handleEvent(string $eventName, array $data): void
    {
        if (!Config::get('cortex.events.logging.enabled', false)) {
            return;
        }

        $event = $data[0] ?? null;

        if (!$event instanceof CortexEvent) {
            return;
        }

        $allowedEvents = Config::get('cortex.events.logging.events', []);

        if (!empty($allowedEvents) && !in_array(get_class($event), $allowedEvents, true)) {
            return;
        }

        $channel = Config::get('cortex.events.logging.channel', 'cortex');
        $level = Config::get('cortex.events.logging.level', 'debug');

        Log::channel($channel)->log($level, $eventName, [
            'timestamp' => $event->timestamp,
            'tenant_id' => $event->tenantId,
            'correlation_id' => $event->correlationId,
            'metadata' => $event->metadata,
        ]);
    }
}
```

---

## 2. Multi-Tenancy Support

### 2.1 Contracts

Create `src/Contracts/TenantContextContract.php`:

```php
<?php

declare(strict_types=1);

namespace Cortex\Contracts;

interface TenantContextContract
{
    public function id(): string|int|null;
    public function getProviderConfig(string $provider): array;
    public function getApiKey(string $provider): ?string;
    public function getSettings(): array;
}
```

Create `src/Contracts/TenantResolverContract.php`:

```php
<?php

declare(strict_types=1);

namespace Cortex\Contracts;

interface TenantResolverContract
{
    public function resolve(): ?TenantContextContract;
}
```

### 2.2 Default Implementation

Create `src/Support/TenantContext.php`:

```php
<?php

declare(strict_types=1);

namespace Cortex\Support;

use Cortex\Contracts\TenantContextContract;
use Spatie\LaravelData\Data;

class TenantContext extends Data implements TenantContextContract
{
    public function __construct(
        protected string|int|null $tenantId = null,
        protected array $providerConfigs = [],
        protected array $apiKeys = [],
        protected array $settings = [],
    ) {}

    public function id(): string|int|null
    {
        return $this->tenantId;
    }

    public function getProviderConfig(string $provider): array
    {
        return $this->providerConfigs[$provider] ?? [];
    }

    public function getApiKey(string $provider): ?string
    {
        return $this->apiKeys[$provider] ?? null;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }
}
```

Create `src/Support/NullTenantResolver.php`:

```php
<?php

declare(strict_types=1);

namespace Cortex\Support;

use Cortex\Contracts\TenantContextContract;
use Cortex\Contracts\TenantResolverContract;

class NullTenantResolver implements TenantResolverContract
{
    public function resolve(): ?TenantContextContract
    {
        return null;
    }
}
```

### 2.3 Tenant Manager

Create `src/Support/TenantManager.php`:

```php
<?php

declare(strict_types=1);

namespace Cortex\Support;

use Closure;
use Cortex\Contracts\TenantContextContract;
use Cortex\Contracts\TenantResolverContract;

class TenantManager
{
    protected ?TenantContextContract $currentTenant = null;

    public function __construct(
        protected TenantResolverContract $resolver,
    ) {}

    public function current(): ?TenantContextContract
    {
        return $this->currentTenant ?? $this->resolver->resolve();
    }

    public function set(TenantContextContract $tenant): void
    {
        $this->currentTenant = $tenant;
    }

    public function clear(): void
    {
        $this->currentTenant = null;
    }

    public function withTenant(TenantContextContract $tenant, Closure $callback): mixed
    {
        $previous = $this->currentTenant;
        $this->currentTenant = $tenant;

        try {
            return $callback();
        } finally {
            $this->currentTenant = $previous;
        }
    }
}
```

### 2.4 Update Cortex Facade

Add to `src/Cortex.php`:

```php
public static function forTenant(TenantContextContract $tenant): static
{
    app(TenantManager::class)->set($tenant);
    return new static();
}

public static function withTenant(TenantContextContract $tenant, Closure $callback): mixed
{
    return app(TenantManager::class)->withTenant($tenant, $callback);
}

public static function tenant(): ?TenantContextContract
{
    return app(TenantManager::class)->current();
}
```

### 2.5 Update ProviderRegistry

Modify `ProviderRegistry` to check tenant context for configuration overrides:

```php
public function get(string $id): ProviderContract
{
    $provider = $this->providers[$id] ?? throw ProviderNotFoundException::forId($id);

    $tenant = app(TenantManager::class)->current();

    if ($tenant) {
        $tenantConfig = $tenant->getProviderConfig($id);
        $apiKey = $tenant->getApiKey($id);

        if (!empty($tenantConfig) || $apiKey) {
            return $provider->withConfig(array_merge(
                $tenantConfig,
                $apiKey ? ['api_key' => $apiKey] : []
            ));
        }
    }

    return $provider;
}
```

### 2.6 Configuration

Add to `config/cortex.php`:

```php
'tenancy' => [
    'enabled' => false,
    'resolver' => \Cortex\Support\NullTenantResolver::class,
],
```

### 2.7 Service Provider Registration

Update `CortexServiceProvider` to register tenancy bindings:

```php
$this->app->singleton(TenantResolverContract::class, function ($app) {
    $resolverClass = config('cortex.tenancy.resolver', NullTenantResolver::class);
    return $app->make($resolverClass);
});

$this->app->singleton(TenantManager::class, function ($app) {
    return new TenantManager($app->make(TenantResolverContract::class));
});
```

---

## 3. Prompt Plugin

### 3.1 Directory Structure

```
src/Plugins/Prompt/
├── PromptPlugin.php
├── Prompt.php
├── PromptRegistry.php
├── FilePromptLoader.php
├── Contracts/
│   ├── PromptContract.php
│   └── PromptRegistryContract.php
└── Exceptions/
    ├── PromptNotFoundException.php
    └── PromptValidationException.php
```

### 3.2 Contracts

Create `src/Plugins/Prompt/Contracts/PromptContract.php`:

```php
<?php

declare(strict_types=1);

namespace Cortex\Plugins\Prompt\Contracts;

use Cortex\Plugins\Schema\ValidationResult;

interface PromptContract
{
    public function id(): string;
    public function name(): string;
    public function version(): string;
    public function template(): string;
    public function variables(): array;
    public function render(array $variables = []): string;
    public function validate(array $variables): ValidationResult;
}
```

Create `src/Plugins/Prompt/Contracts/PromptRegistryContract.php`:

```php
<?php

declare(strict_types=1);

namespace Cortex\Plugins\Prompt\Contracts;

use Illuminate\Support\Collection;

interface PromptRegistryContract
{
    public function register(PromptContract $prompt): void;
    public function get(string $id, ?string $version = null): PromptContract;
    public function has(string $id): bool;
    public function versions(string $id): Collection;
    public function latest(string $id): PromptContract;
    public function all(): Collection;
}
```

### 3.3 Prompt Class

Create `src/Plugins/Prompt/Prompt.php`:

```php
<?php

declare(strict_types=1);

namespace Cortex\Plugins\Prompt;

use Cortex\Plugins\Prompt\Contracts\PromptContract;
use Cortex\Plugins\Schema\ValidationResult;
use Illuminate\Support\Facades\Blade;
use Spatie\LaravelData\Data;

class Prompt extends Data implements PromptContract
{
    public function __construct(
        public string $id,
        public string $template,
        public array $requiredVariables = [],
        public array $defaults = [],
        public ?string $version = '1.0.0',
        public ?string $name = null,
        public array $metadata = [],
    ) {
        $this->name ??= $this->id;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name ?? $this->id;
    }

    public function version(): string
    {
        return $this->version ?? '1.0.0';
    }

    public function template(): string
    {
        return $this->template;
    }

    public function variables(): array
    {
        return $this->requiredVariables;
    }

    public function render(array $variables = []): string
    {
        $this->validate($variables)->throw();

        $merged = array_merge($this->defaults, $variables);

        return Blade::render($this->template, $merged);
    }

    public function validate(array $variables): ValidationResult
    {
        $errors = [];

        foreach ($this->requiredVariables as $required) {
            if (!array_key_exists($required, $variables) && !array_key_exists($required, $this->defaults)) {
                $errors[] = "Missing required variable: {$required}";
            }
        }

        return new ValidationResult(
            valid: empty($errors),
            errors: $errors,
        );
    }
}
```

### 3.4 Prompt Registry

Create `src/Plugins/Prompt/PromptRegistry.php`:

```php
<?php

declare(strict_types=1);

namespace Cortex\Plugins\Prompt;

use Cortex\Plugins\Prompt\Contracts\PromptContract;
use Cortex\Plugins\Prompt\Contracts\PromptRegistryContract;
use Cortex\Plugins\Prompt\Exceptions\PromptNotFoundException;
use Illuminate\Support\Collection;

class PromptRegistry implements PromptRegistryContract
{
    /** @var array<string, array<string, PromptContract>> */
    protected array $prompts = [];

    public function register(PromptContract $prompt): void
    {
        $this->prompts[$prompt->id()][$prompt->version()] = $prompt;
    }

    public function get(string $id, ?string $version = null): PromptContract
    {
        if (!$this->has($id)) {
            throw PromptNotFoundException::forId($id);
        }

        if ($version === null) {
            return $this->latest($id);
        }

        if (!isset($this->prompts[$id][$version])) {
            throw PromptNotFoundException::forVersion($id, $version);
        }

        return $this->prompts[$id][$version];
    }

    public function has(string $id): bool
    {
        return isset($this->prompts[$id]) && !empty($this->prompts[$id]);
    }

    public function versions(string $id): Collection
    {
        if (!$this->has($id)) {
            return collect();
        }

        return collect(array_keys($this->prompts[$id]))->sort()->values();
    }

    public function latest(string $id): PromptContract
    {
        if (!$this->has($id)) {
            throw PromptNotFoundException::forId($id);
        }

        $versions = $this->versions($id);
        $latestVersion = $versions->last();

        return $this->prompts[$id][$latestVersion];
    }

    public function all(): Collection
    {
        return collect($this->prompts)->map(fn ($versions) => collect($versions)->last());
    }
}
```

### 3.5 File Prompt Loader

Create `src/Plugins/Prompt/FilePromptLoader.php`:

```php
<?php

declare(strict_types=1);

namespace Cortex\Plugins\Prompt;

use Cortex\Plugins\Prompt\Contracts\PromptRegistryContract;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

class FilePromptLoader
{
    public function __construct(
        protected PromptRegistryContract $registry,
    ) {}

    public function loadFromPath(string $path): void
    {
        if (!File::isDirectory($path)) {
            return;
        }

        foreach (File::directories($path) as $promptDir) {
            $this->loadPromptDirectory($promptDir);
        }
    }

    protected function loadPromptDirectory(string $dir): void
    {
        $metadataFile = $dir . '/prompt.yaml';
        $metadata = [];

        if (File::exists($metadataFile)) {
            $metadata = Yaml::parseFile($metadataFile);
        }

        $id = $metadata['id'] ?? basename($dir);
        $name = $metadata['name'] ?? $id;
        $requiredVariables = $metadata['required_variables'] ?? [];
        $defaults = $metadata['defaults'] ?? [];

        // Load all version files
        foreach (File::glob($dir . '/v*.blade.php') as $versionFile) {
            $version = $this->extractVersion($versionFile);
            $template = File::get($versionFile);

            $prompt = new Prompt(
                id: $id,
                template: $template,
                requiredVariables: $requiredVariables,
                defaults: $defaults,
                version: $version,
                name: $name,
                metadata: $metadata,
            );

            $this->registry->register($prompt);
        }
    }

    protected function extractVersion(string $filename): string
    {
        $basename = basename($filename, '.blade.php');

        // Extract version from filename like v1, v2, v1.0, v1.0.0
        if (preg_match('/^v?(\d+(?:\.\d+(?:\.\d+)?)?)$/', $basename, $matches)) {
            return $matches[1];
        }

        return '1.0.0';
    }
}
```

### 3.6 Prompt Plugin

Create `src/Plugins/Prompt/PromptPlugin.php`:

```php
<?php

declare(strict_types=1);

namespace Cortex\Plugins\Prompt;

use Cortex\Contracts\PluginContract;
use Cortex\Plugins\Prompt\Contracts\PromptRegistryContract;
use Cortex\Support\PluginManager;
use Illuminate\Support\Facades\Config;

class PromptPlugin implements PluginContract
{
    public function id(): string
    {
        return 'prompt';
    }

    public function name(): string
    {
        return 'Prompt Plugin';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function dependencies(): array
    {
        return [];
    }

    public function provides(): array
    {
        return ['prompt', 'prompt-registry'];
    }

    public function register(PluginManager $manager): void
    {
        $manager->getContainer()->singleton(PromptRegistryContract::class, PromptRegistry::class);
    }

    public function boot(PluginManager $manager): void
    {
        if (Config::get('cortex.prompt.discovery.enabled', true)) {
            $loader = new FilePromptLoader(
                $manager->getContainer()->make(PromptRegistryContract::class)
            );

            foreach (Config::get('cortex.prompt.discovery.paths', []) as $path) {
                $loader->loadFromPath($path);
            }
        }
    }
}
```

### 3.7 Configuration

Add to `config/cortex.php`:

```php
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

### 3.8 Cortex Facade Method

Add to `src/Cortex.php`:

```php
public static function prompt(string $id, ?string $version = null): PromptContract
{
    return app(PromptRegistryContract::class)->get($id, $version);
}

public static function prompts(): PromptRegistryContract
{
    return app(PromptRegistryContract::class);
}
```

---

## 4. Agent Async Execution

### 4.1 PendingAgentRun Job

Create `src/Plugins/Agent/PendingAgentRun.php`:

```php
<?php

declare(strict_types=1);

namespace Cortex\Plugins\Agent;

use Cortex\Events\Agent\AgentRunCompleted;
use Cortex\Events\Agent\AgentRunFailed;
use Cortex\Plugins\Agent\Contracts\AgentContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PendingAgentRun implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $runId;
    protected ?string $broadcastChannel = null;

    public function __construct(
        public AgentContract|string $agent,
        public string|array $input,
        public ?AgentContext $context = null,
    ) {
        $this->runId = Str::uuid()->toString();
    }

    public function id(): string
    {
        return $this->runId;
    }

    public function onQueue(string $queue): static
    {
        $this->queue = $queue;
        return $this;
    }

    public function broadcastTo(string $channel): static
    {
        $this->broadcastChannel = $channel;
        return $this;
    }

    public function handle(): void
    {
        $this->updateStatus(AgentRunStatus::Running);

        try {
            $agent = is_string($this->agent)
                ? app(AgentRegistryContract::class)->get($this->agent)
                : $this->agent;

            $response = $agent->run($this->input, $this->context);

            $this->storeResult($response);
            $this->updateStatus(AgentRunStatus::Completed);

            event(new AgentRunCompleted(
                agent: $agent,
                input: $this->input,
                output: $response->output,
                iterations: $response->iterations,
            ));

            if ($this->broadcastChannel) {
                broadcast(new AgentRunCompletedEvent($this->broadcastChannel, $this->runId, $response));
            }
        } catch (\Throwable $e) {
            $this->updateStatus(AgentRunStatus::Failed, $e->getMessage());

            event(new AgentRunFailed(
                agent: $this->agent,
                input: $this->input,
                exception: $e,
                iterations: 0,
            ));

            throw $e;
        }
    }

    protected function updateStatus(AgentRunStatus $status, ?string $error = null): void
    {
        Cache::put("cortex:agent_run:{$this->runId}:status", [
            'status' => $status->value,
            'error' => $error,
            'updated_at' => now()->toIso8601String(),
        ], now()->addHours(24));
    }

    protected function storeResult(AgentResponse $response): void
    {
        Cache::put("cortex:agent_run:{$this->runId}:result", $response, now()->addHours(24));
    }

    public static function status(string $runId): AgentRunStatus
    {
        $data = Cache::get("cortex:agent_run:{$runId}:status");

        if (!$data) {
            return AgentRunStatus::Pending;
        }

        return AgentRunStatus::from($data['status']);
    }

    public static function result(string $runId): ?AgentResponse
    {
        return Cache::get("cortex:agent_run:{$runId}:result");
    }
}
```

### 4.2 Agent Run Status Enum

Create `src/Plugins/Agent/AgentRunStatus.php`:

```php
<?php

declare(strict_types=1);

namespace Cortex\Plugins\Agent;

enum AgentRunStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isComplete(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }

    public function isRunning(): bool
    {
        return $this === self::Running;
    }
}
```

### 4.3 Update AgentContract

Add to `AgentContract.php`:

```php
public function runAsync(string|array $input, ?AgentContext $context = null): PendingAgentRun;
```

### 4.4 Update Agent Class

Add to `Agent.php`:

```php
public function runAsync(string|array $input, ?AgentContext $context = null): PendingAgentRun
{
    return new PendingAgentRun($this, $input, $context);
}
```

### 4.5 Broadcast Event

Create `src/Plugins/Agent/Events/AgentRunCompletedEvent.php`:

```php
<?php

declare(strict_types=1);

namespace Cortex\Plugins\Agent\Events;

use Cortex\Plugins\Agent\AgentResponse;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentRunCompletedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $channel,
        public string $runId,
        public AgentResponse $response,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel($this->channel)];
    }

    public function broadcastAs(): string
    {
        return 'agent.run.completed';
    }
}
```

---

## 5. Workflow Persistence

### 5.1 Repository Contract

Create `src/Plugins/Workflow/Contracts/WorkflowStateRepositoryContract.php`:

```php
<?php

declare(strict_types=1);

namespace Cortex\Plugins\Workflow\Contracts;

use Cortex\Plugins\Workflow\WorkflowState;
use Illuminate\Support\Collection;

interface WorkflowStateRepositoryContract
{
    public function save(WorkflowState $state): void;
    public function find(string $runId): ?WorkflowState;
    public function findByWorkflow(string $workflowId): Collection;
    public function findByStatus(WorkflowStatus $status): Collection;
    public function delete(string $runId): void;
    public function deleteExpired(): int;
}
```

### 5.2 Database Repository

Create `src/Plugins/Workflow/Repositories/DatabaseWorkflowStateRepository.php`:

```php
<?php

declare(strict_types=1);

namespace Cortex\Plugins\Workflow\Repositories;

use Cortex\Plugins\Workflow\Contracts\WorkflowStateRepositoryContract;
use Cortex\Plugins\Workflow\WorkflowState;
use Cortex\Plugins\Workflow\WorkflowStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class DatabaseWorkflowStateRepository implements WorkflowStateRepositoryContract
{
    protected function table(): string
    {
        return Config::get('cortex.workflow.persistence.table', 'cortex_workflow_states');
    }

    public function save(WorkflowState $state): void
    {
        DB::table($this->table())->updateOrInsert(
            ['run_id' => $state->runId],
            [
                'workflow_id' => $state->workflowId,
                'current_node' => $state->currentNode,
                'status' => $state->status->value,
                'data' => json_encode($state->data),
                'history' => json_encode($state->history),
                'pause_reason' => $state->pauseReason,
                'started_at' => $state->startedAt,
                'paused_at' => $state->pausedAt,
                'completed_at' => $state->completedAt,
                'updated_at' => now(),
            ]
        );
    }

    public function find(string $runId): ?WorkflowState
    {
        $row = DB::table($this->table())->where('run_id', $runId)->first();

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findByWorkflow(string $workflowId): Collection
    {
        return DB::table($this->table())
            ->where('workflow_id', $workflowId)
            ->get()
            ->map(fn ($row) => $this->hydrate($row));
    }

    public function findByStatus(WorkflowStatus $status): Collection
    {
        return DB::table($this->table())
            ->where('status', $status->value)
            ->get()
            ->map(fn ($row) => $this->hydrate($row));
    }

    public function delete(string $runId): void
    {
        DB::table($this->table())->where('run_id', $runId)->delete();
    }

    public function deleteExpired(): int
    {
        $ttl = Config::get('cortex.workflow.persistence.ttl', 86400 * 7);

        return DB::table($this->table())
            ->where('updated_at', '<', now()->subSeconds($ttl))
            ->whereIn('status', [WorkflowStatus::Completed->value, WorkflowStatus::Failed->value])
            ->delete();
    }

    protected function hydrate(object $row): WorkflowState
    {
        return new WorkflowState(
            workflowId: $row->workflow_id,
            runId: $row->run_id,
            currentNode: $row->current_node,
            status: WorkflowStatus::from($row->status),
            data: json_decode($row->data, true) ?? [],
            history: json_decode($row->history, true) ?? [],
            pauseReason: $row->pause_reason,
            startedAt: $row->started_at ? new \DateTimeImmutable($row->started_at) : null,
            pausedAt: $row->paused_at ? new \DateTimeImmutable($row->paused_at) : null,
            completedAt: $row->completed_at ? new \DateTimeImmutable($row->completed_at) : null,
        );
    }
}
```

### 5.3 Cache Repository

Create `src/Plugins/Workflow/Repositories/CacheWorkflowStateRepository.php`:

```php
<?php

declare(strict_types=1);

namespace Cortex\Plugins\Workflow\Repositories;

use Cortex\Plugins\Workflow\Contracts\WorkflowStateRepositoryContract;
use Cortex\Plugins\Workflow\WorkflowState;
use Cortex\Plugins\Workflow\WorkflowStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class CacheWorkflowStateRepository implements WorkflowStateRepositoryContract
{
    protected function ttl(): int
    {
        return Config::get('cortex.workflow.persistence.ttl', 86400 * 7);
    }

    protected function key(string $runId): string
    {
        return "cortex:workflow_state:{$runId}";
    }

    protected function indexKey(string $workflowId): string
    {
        return "cortex:workflow_index:{$workflowId}";
    }

    public function save(WorkflowState $state): void
    {
        Cache::put($this->key($state->runId), $state, $this->ttl());

        // Maintain index of runs per workflow
        $index = Cache::get($this->indexKey($state->workflowId), []);
        $index[$state->runId] = true;
        Cache::put($this->indexKey($state->workflowId), $index, $this->ttl());
    }

    public function find(string $runId): ?WorkflowState
    {
        return Cache::get($this->key($runId));
    }

    public function findByWorkflow(string $workflowId): Collection
    {
        $index = Cache::get($this->indexKey($workflowId), []);

        return collect(array_keys($index))
            ->map(fn ($runId) => $this->find($runId))
            ->filter();
    }

    public function findByStatus(WorkflowStatus $status): Collection
    {
        // Note: This is inefficient for cache driver - consider using database for status queries
        return collect();
    }

    public function delete(string $runId): void
    {
        $state = $this->find($runId);

        if ($state) {
            $index = Cache::get($this->indexKey($state->workflowId), []);
            unset($index[$runId]);
            Cache::put($this->indexKey($state->workflowId), $index, $this->ttl());
        }

        Cache::forget($this->key($runId));
    }

    public function deleteExpired(): int
    {
        // Cache handles TTL automatically
        return 0;
    }
}
```

### 5.4 Database Migration

Create `database/migrations/2024_01_01_000002_create_cortex_workflow_states_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cortex_workflow_states', function (Blueprint $table) {
            $table->id();
            $table->string('run_id')->unique();
            $table->string('workflow_id')->index();
            $table->string('current_node');
            $table->string('status')->index();
            $table->json('data');
            $table->json('history');
            $table->string('pause_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['workflow_id', 'status']);
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cortex_workflow_states');
    }
};
```

### 5.5 Update WorkflowEngine

Integrate the repository into the workflow engine for automatic persistence:

```php
// In WorkflowEngine::execute()
protected function persistState(WorkflowState $state): void
{
    if ($this->repository) {
        $this->repository->save($state);
    }
}

// Call after each node execution and on pause/complete/fail
```

### 5.6 Resume Capability

Add to `WorkflowEngine`:

```php
public function resume(string $runId, array $input = []): WorkflowResult
{
    $state = $this->repository->find($runId);

    if (!$state) {
        throw WorkflowNotFoundException::forRunId($runId);
    }

    if ($state->status !== WorkflowStatus::Paused) {
        throw WorkflowNotPausedException::forRunId($runId);
    }

    // Dispatch resume event
    $this->dispatchCortexEvent(new WorkflowResumed(
        workflow: $this->workflow,
        state: $state,
        input: $input,
    ));

    // Merge input and continue execution
    $state = $state->with(
        status: WorkflowStatus::Running,
        data: array_merge($state->data, ['resume_input' => $input]),
    );

    return $this->continueExecution($state);
}
```

---

## 6. RAG Integration

### 6.1 Retriever Implementations

Create `src/Plugins/Agent/Retrievers/CallbackRetriever.php`:

```php
<?php

declare(strict_types=1);

namespace Cortex\Plugins\Agent\Retrievers;

use Closure;
use Cortex\Plugins\Agent\Contracts\RetrieverContract;
use Cortex\Plugins\Agent\RetrievedContent;

class CallbackRetriever implements RetrieverContract
{
    public function __construct(
        protected Closure $callback,
    ) {}

    public function retrieve(string $query, int $limit = 5): RetrievedContent
    {
        return ($this->callback)($query, $limit);
    }
}
```

Create `src/Plugins/Agent/Retrievers/EloquentRetriever.php`:

```php
<?php

declare(strict_types=1);

namespace Cortex\Plugins\Agent\Retrievers;

use Closure;
use Cortex\Plugins\Agent\Contracts\RetrieverContract;
use Cortex\Plugins\Agent\RetrievedContent;
use Cortex\Plugins\Agent\RetrievedItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EloquentRetriever implements RetrieverContract
{
    protected ?Closure $queryModifier = null;
    protected string $contentColumn = 'content';
    protected ?string $scoreColumn = null;

    public function __construct(
        protected string $model,
        protected array $searchColumns,
    ) {}

    public function contentColumn(string $column): static
    {
        $this->contentColumn = $column;
        return $this;
    }

    public function scoreColumn(string $column): static
    {
        $this->scoreColumn = $column;
        return $this;
    }

    public function modifyQuery(Closure $modifier): static
    {
        $this->queryModifier = $modifier;
        return $this;
    }

    public function retrieve(string $query, int $limit = 5): RetrievedContent
    {
        /** @var Model $model */
        $model = new $this->model;

        $builder = $model->newQuery();

        // Build search query
        $builder->where(function (Builder $q) use ($query) {
            foreach ($this->searchColumns as $column) {
                $q->orWhere($column, 'LIKE', "%{$query}%");
            }
        });

        // Apply custom modifications
        if ($this->queryModifier) {
            ($this->queryModifier)($builder, $query);
        }

        $results = $builder->limit($limit)->get();

        $items = $results->map(function ($row) {
            return new RetrievedItem(
                content: $row->{$this->contentColumn},
                score: $this->scoreColumn ? (float) $row->{$this->scoreColumn} : 1.0,
                metadata: $row->toArray(),
            );
        })->all();

        return new RetrievedContent(items: $items);
    }
}
```

Create `src/Plugins/Agent/Retrievers/CollectionRetriever.php`:

```php
<?php

declare(strict_types=1);

namespace Cortex\Plugins\Agent\Retrievers;

use Cortex\Plugins\Agent\Contracts\RetrieverContract;
use Cortex\Plugins\Agent\RetrievedContent;
use Cortex\Plugins\Agent\RetrievedItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CollectionRetriever implements RetrieverContract
{
    public function __construct(
        protected Collection $items,
        protected string $contentKey = 'content',
    ) {}

    public function retrieve(string $query, int $limit = 5): RetrievedContent
    {
        $query = Str::lower($query);
        $queryWords = explode(' ', $query);

        $scored = $this->items->map(function ($item) use ($queryWords) {
            $content = is_array($item) ? ($item[$this->contentKey] ?? '') : (string) $item;
            $contentLower = Str::lower($content);

            // Simple scoring: count matching words
            $score = 0;
            foreach ($queryWords as $word) {
                if (strlen($word) > 2 && Str::contains($contentLower, $word)) {
                    $score++;
                }
            }

            return [
                'content' => $content,
                'score' => $score / max(count($queryWords), 1),
                'metadata' => is_array($item) ? $item : [],
            ];
        })
        ->filter(fn ($item) => $item['score'] > 0)
        ->sortByDesc('score')
        ->take($limit)
        ->values();

        $items = $scored->map(fn ($item) => new RetrievedItem(
            content: $item['content'],
            score: $item['score'],
            metadata: $item['metadata'],
        ))->all();

        return new RetrievedContent(items: $items);
    }
}
```

### 6.2 Agent Integration

Update `Agent.php` to use retrievers:

```php
protected ?RetrieverContract $retriever = null;

public function retriever(RetrieverContract $retriever): static
{
    $this->retriever = $retriever;
    return $this;
}

// In the agent loop, before sending to LLM:
protected function augmentWithContext(string $input): string
{
    if (!$this->retriever) {
        return $input;
    }

    $retrieved = $this->retriever->retrieve($input);

    if ($retrieved->isEmpty()) {
        return $input;
    }

    return $input . "\n\n---\nRelevant Context:\n" . $retrieved->toContext();
}
```

---

## 7. Testing Requirements

### 7.1 Event System Tests

```php
// tests/Unit/Events/CortexEventTest.php
it('creates event with timestamp and metadata', function () {
    $event = new BeforeChatSend(
        request: $request,
        tenantId: 'tenant-1',
        correlationId: 'corr-123',
    );

    expect($event->timestamp)->toBeFloat();
    expect($event->tenantId)->toBe('tenant-1');
    expect($event->correlationId)->toBe('corr-123');
});

it('dispatches events when enabled', function () {
    Event::fake();

    // Trigger action that should dispatch event
    Cortex::chat()->send($request);

    Event::assertDispatched(BeforeChatSend::class);
    Event::assertDispatched(AfterChatReceive::class);
});

it('does not dispatch disabled events', function () {
    config(['cortex.events.disabled' => [ChatStreamChunk::class]]);

    Event::fake();

    // Trigger streaming
    foreach (Cortex::chat()->stream($request) as $chunk) {
        // consume
    }

    Event::assertNotDispatched(ChatStreamChunk::class);
    Event::assertDispatched(ChatStreamCompleted::class);
});
```

### 7.2 Multi-Tenancy Tests

```php
// tests/Feature/TenancyTest.php
it('resolves tenant-specific provider config', function () {
    $tenant = new TenantContext(
        tenantId: 'tenant-1',
        providerConfigs: ['bedrock' => ['region' => 'eu-west-1']],
        apiKeys: ['bedrock' => 'tenant-api-key'],
    );

    Cortex::withTenant($tenant, function () {
        $provider = Cortex::provider('bedrock');

        expect($provider->config()['region'])->toBe('eu-west-1');
    });
});
```

### 7.3 Prompt Plugin Tests

```php
// tests/Unit/Plugins/Prompt/PromptTest.php
it('renders prompt with variables', function () {
    $prompt = new Prompt(
        id: 'test',
        template: 'Hello {{ $name }}, you are {{ $age }} years old.',
        requiredVariables: ['name', 'age'],
    );

    $rendered = $prompt->render(['name' => 'John', 'age' => 30]);

    expect($rendered)->toBe('Hello John, you are 30 years old.');
});

it('validates required variables', function () {
    $prompt = new Prompt(
        id: 'test',
        template: '{{ $name }}',
        requiredVariables: ['name'],
    );

    $result = $prompt->validate([]);

    expect($result->valid)->toBeFalse();
    expect($result->errors)->toContain('Missing required variable: name');
});
```

### 7.4 Workflow Persistence Tests

```php
// tests/Feature/Plugins/Workflow/PersistenceTest.php
it('persists workflow state to database', function () {
    $workflow = createTestWorkflow();

    $result = $workflow->run(['input' => 'test']);

    $this->assertDatabaseHas('cortex_workflow_states', [
        'workflow_id' => $workflow->id(),
        'status' => 'completed',
    ]);
});

it('resumes paused workflow', function () {
    $workflow = createWorkflowWithHumanInput();

    $result = $workflow->run(['input' => 'test']);

    expect($result->status)->toBe(WorkflowStatus::Paused);

    $resumed = $workflow->resume($result->runId, ['approved' => true]);

    expect($resumed->status)->toBe(WorkflowStatus::Completed);
});
```

---

## 8. Documentation Updates

Update the following documentation files:

1. `docs/plugins/events.md` - Document all events and configuration
2. `docs/tenancy.md` - Document multi-tenancy setup
3. `docs/plugins/prompt.md` - Document prompt plugin usage
4. `docs/plugins/agent.md` - Add async execution section
5. `docs/plugins/workflow.md` - Add persistence and resume sections

---

## 9. Implementation Order

1. **Event System** (foundation for observability)
   - CortexEvent base class
   - All event classes
   - Event dispatcher trait
   - Integration into existing plugins
   - Event logging subscriber
   - Configuration

2. **Multi-Tenancy** (enables per-tenant configuration)
   - Contracts
   - TenantContext and TenantManager
   - ProviderRegistry integration
   - Cortex facade methods
   - Configuration

3. **Prompt Plugin** (standalone, no dependencies)
   - Contracts
   - Prompt class
   - PromptRegistry
   - FilePromptLoader
   - PromptPlugin
   - Configuration

4. **Agent Async** (depends on events)
   - PendingAgentRun job
   - AgentRunStatus enum
   - AgentContract update
   - Broadcast events

5. **Workflow Persistence** (depends on events)
   - Repository contract
   - Database repository
   - Cache repository
   - Migration
   - WorkflowEngine integration
   - Resume capability

6. **RAG Integration** (depends on agent updates)
   - CallbackRetriever
   - EloquentRetriever
   - CollectionRetriever
   - Agent integration

---

## 10. Estimated File Count

| Component | New Files | Modified Files |
|-----------|-----------|----------------|
| Event System | ~35 | ~10 |
| Multi-Tenancy | ~5 | ~5 |
| Prompt Plugin | ~8 | ~2 |
| Agent Async | ~4 | ~3 |
| Workflow Persistence | ~4 | ~3 |
| RAG Integration | ~3 | ~2 |
| **Total** | **~59** | **~25** |
