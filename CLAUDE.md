# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Cortex is a Laravel 12 package for interacting with Large Language Models (LLMs). It provides a fluent API built around a modular plugin system with 14 plugins (3 core, 11 optional).

**Namespace:** `JayI\Cortex`
**PHP Version:** 8.2+

## Development Commands

```bash
composer test              # Run Pest test suite
composer analyse           # Run PHPStan static analysis
composer format            # Format code with Laravel Pint
composer build             # Prepare and build workbench
composer start             # Start development server
```

To run a single test file:
```bash
./vendor/bin/pest tests/Unit/Plugins/Tool/ToolBuilderTest.php
```

To run tests matching a pattern:
```bash
./vendor/bin/pest --filter="test name pattern"
```

## Architecture

### Plugin System

The core abstraction is a plugin system with dependency resolution (topological sort). Plugins communicate via extension points and hooks.

**Core Plugins (always loaded):**
- **Schema** - JSON Schema generation and validation
- **Provider** - LLM provider abstraction (AWS Bedrock)
- **Chat** - Chat completion with streaming support

**Optional Plugins (enabled via config `cortex.plugins.enabled`):**
tool, structured-output, agent, workflow, mcp, guardrail, resilience, prompt, usage, cache, context

### Plugin Lifecycle

1. **Registration** (`register()`) - Plugins register container bindings and extension points. Cannot resolve other plugin dependencies yet.
2. **Boot** (`boot()`) - Called after all plugins registered, in dependency order (topological sort). Safe to resolve cross-plugin dependencies.

### Key Entry Points

- `src/CortexServiceProvider.php` - Service provider, registers plugins from config
- `src/CortexManager.php` - Main public API, accessed via `Cortex` facade
- `src/Support/PluginManager.php` - Plugin registration, boot, and dependency resolution
- `config/cortex.php` - All configuration with extensive comments

### Plugin Structure

Each plugin follows this pattern:
```
src/Plugins/{PluginName}/
├── {PluginName}Plugin.php        # Implements PluginContract
├── {PluginName}Registry.php      # Implements AbstractRegistry (if applicable)
├── {PluginName}Collection.php    # Domain-specific collection class
├── Contracts/                    # Interfaces ({PluginName}Contract, {PluginName}RegistryContract)
├── Data/                         # DTOs using spatie/laravel-data
├── Exceptions/                   # Plugin-specific exceptions
└── ...                           # Implementation classes
```

Plugins implement `PluginContract` with methods: `id()`, `name()`, `version()`, `dependencies()`, `provides()`, `register()`, `boot()`.

### Registry Pattern

Most plugins use a Registry to manage their resources. Registries implement `AbstractRegistry` providing:
- `register(mixed $item)` - Add an item
- `get(string $id)` - Retrieve by ID
- `has(string $id)` - Check existence
- `all()` / `only()` / `except()` / `filter()` - Query methods
- `discover(array $paths)` - Auto-discovery from directories

### Collection Classes

Domain-specific collections (e.g., `ToolCollection`, `AgentCollection`) implement `Arrayable`, `Countable`, `IteratorAggregate` with fluent methods: `add()`, `remove()`, `get()`, `has()`, `only()`, `except()`, `filter()`, `merge()`, `map()`.

### Cross-Plugin Dependencies

Use the `RequiresPlugins` trait for methods that depend on optional plugins:
```php
use JayI\Cortex\Support\Concerns\RequiresPlugins;

class MyClass {
    use RequiresPlugins;

    public function withTools($tools) {
        $this->ensurePluginEnabled('tool'); // Throws PluginException if disabled
        // ...
    }
}
```

### Extension Points and Hooks

- **Extension Points** - Explicit places for plugins to inject functionality (e.g., `providers`, `tools`)
- **Hooks** - Data modification at specific points: `chat.before_send`, `chat.after_receive`, `tool.before_execute`, `tool.after_execute`

## Testing

- **Framework:** Pest PHP v3 with Orchestra Testbench
- **Database:** In-memory SQLite for tests
- **Mocking:** Mockery + `FakeProvider` for testing without real API calls
- **Base class:** `tests/TestCase.php` sets up the package with `CortexServiceProvider`

Use `FakeProvider::text('response')` for testing:
```php
$fake = FakeProvider::text('Mocked response');
$response = $fake->chat($request);
$fake->assertSentCount(1);
```

## Code Standards

- Strict types enabled (`declare(strict_types=1)`)
- PSR-4 autoloading
- Heavy use of interfaces/contracts in `Contracts/` directories
- Factory methods for exception creation (e.g., `PluginException::disabled($id)`)
- DTOs via Spatie Laravel Data

## Documentation

Plugin-specific documentation exists in `docs/plugins/` for each plugin. The overall plugin system is documented in `docs/plugin-system.md`.
