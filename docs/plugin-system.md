# Plugin System

The plugin system is the foundation of Cortex's extensibility. Plugins are self-contained units that can register new functionality, extend existing functionality, replace core implementations, and depend on other plugins.

## Overview

Cortex uses a modular plugin architecture where:

- **Core plugins** (Schema, Provider, Chat) are always loaded
- **Optional plugins** can be enabled via configuration
- Plugins declare dependencies and are booted in topological order
- Plugins can extend each other via hooks and extension points

## Plugin Contract

Every plugin implements the `PluginContract` interface:

```php
use JayI\Cortex\Contracts\PluginContract;
use JayI\Cortex\Contracts\PluginManagerContract;

class MyPlugin implements PluginContract
{
    public function id(): string
    {
        return 'my-plugin';
    }

    public function name(): string
    {
        return 'My Plugin';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function dependencies(): array
    {
        return ['schema']; // Depends on schema plugin
    }

    public function provides(): array
    {
        return ['my-feature']; // Features this plugin provides
    }

    public function register(PluginManagerContract $manager): void
    {
        // Register bindings, extension points, hooks
    }

    public function boot(PluginManagerContract $manager): void
    {
        // Bootstrap after all plugins registered
    }
}
```

## Plugin Manager

The `PluginManager` handles plugin lifecycle:

```php
use JayI\Cortex\Contracts\PluginManagerContract;

// Check if a plugin is registered
$manager->has('tool'); // bool

// Get a plugin
$plugin = $manager->get('tool');

// Check if a feature is provided
$manager->hasFeature('tools'); // bool

// Get the plugin providing a feature
$provider = $manager->getFeatureProvider('tools');
```

## Extension Points

Extension points are explicit places where plugins can inject functionality:

```php
use JayI\Cortex\Support\ExtensionPoint;

// In your plugin's register method
public function register(PluginManagerContract $manager): void
{
    // Register an extension point
    $manager->registerExtensionPoint(
        'my-extensions',
        ExtensionPoint::make('my-extensions', MyExtensionInterface::class)
    );
}

// Other plugins can extend it
public function register(PluginManagerContract $manager): void
{
    $manager->extend('my-extensions', new MyExtension());
}

// Access extensions
$point = $manager->getExtensionPoint('my-extensions');
foreach ($point->all() as $extension) {
    // Use extension
}
```

**Standard Extension Points:**

| Extension Point | Type | Description |
|-----------------|------|-------------|
| `providers` | `ProviderContract` | LLM providers |
| `tools` | `ToolContract` | Available tools |

## Hooks

Hooks allow data modification at specific points in the request lifecycle:

```php
// Register a hook with priority (higher runs first)
$manager->addHook('chat.before_send', function (ChatRequest $request) {
    // Modify request
    return $request;
}, priority: 10);

// Apply hooks to filter data
$request = $manager->applyHooks('chat.before_send', $request);
```

**Standard Hooks:**

| Hook | Arguments | Description |
|------|-----------|-------------|
| `chat.before_send` | `ChatRequest` | Before sending chat request |
| `chat.after_receive` | `ChatResponse` | After receiving response |
| `tool.before_execute` | `$input, $tool, $context` | Before tool execution |
| `tool.after_execute` | `$result, $tool, $input, $context` | After tool execution |

## Replacements

Plugins can replace bound implementations:

```php
$manager->replace(ChatClientContract::class, MyCustomChatClient::class);
```

## Configuration

Enable/disable plugins in `config/cortex.php`:

```php
'plugins' => [
    'enabled' => [
        'tool',
        'structured-output',
        // Add optional plugins here
    ],
    'disabled' => [
        // Explicitly disabled plugins
    ],
],
```

## Creating a Custom Plugin

1. **Create the plugin class:**

```php
<?php

namespace App\Cortex\Plugins;

use Illuminate\Contracts\Container\Container;
use JayI\Cortex\Contracts\PluginContract;
use JayI\Cortex\Contracts\PluginManagerContract;

class CustomPlugin implements PluginContract
{
    public function __construct(
        protected Container $container,
        protected array $config = [],
    ) {}

    public function id(): string
    {
        return 'custom';
    }

    public function name(): string
    {
        return 'Custom Plugin';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function dependencies(): array
    {
        return ['chat']; // Require chat plugin
    }

    public function provides(): array
    {
        return ['custom-feature'];
    }

    public function register(PluginManagerContract $manager): void
    {
        // Register services
        $this->container->singleton(CustomServiceContract::class, CustomService::class);

        // Add hooks
        $manager->addHook('chat.before_send', function ($request) {
            // Modify all chat requests
            return $request;
        });
    }

    public function boot(PluginManagerContract $manager): void
    {
        // Bootstrap logic
    }
}
```

2. **Register in the service provider:**

```php
// In a service provider
use JayI\Cortex\Contracts\PluginManagerContract;

public function boot(): void
{
    $manager = $this->app->make(PluginManagerContract::class);
    $manager->register(new CustomPlugin($this->app, config('cortex.custom', [])));
}
```

## Plugin Lifecycle

1. **Registration Phase** (`register()`)
   - Plugins register bindings and extension points
   - Cannot resolve other plugin dependencies yet
   - Order is determined by configuration

2. **Boot Phase** (`boot()`)
   - Called after all plugins are registered
   - Plugins are booted in dependency order (topological sort)
   - Safe to resolve dependencies from other plugins

## Dependency Resolution

Plugins declare dependencies via `dependencies()`. The plugin manager:

1. Validates all dependencies exist
2. Detects circular dependencies
3. Sorts plugins topologically
4. Boots in correct order

```php
// Plugin A has no dependencies
// Plugin B depends on A
// Plugin C depends on B
// Boot order: A -> B -> C
```

## Error Handling

The plugin system throws `PluginException` for common errors:

```php
use JayI\Cortex\Exceptions\PluginException;

// Plugin already registered
PluginException::alreadyRegistered($pluginId);

// Cannot register after boot
PluginException::alreadyBooted($pluginId);

// Missing dependency
PluginException::dependencyNotFound($pluginId, $dependencyId);

// Circular dependency detected
PluginException::circularDependency($pluginId);

// Extension point not found
PluginException::extensionPointNotFound($name);
```
