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
- **Provider** - LLM provider abstraction (currently AWS Bedrock)
- **Chat** - Chat completion with streaming support

**Optional Plugins (enabled via config):**
Tool, StructuredOutput, Agent, Workflow, MCP, Resilience, Usage, Guardrail, Cache, ContextManager, Prompt

### Key Entry Points

- `src/CortexServiceProvider.php` - Service provider, registers plugins
- `src/CortexManager.php` - Main public API
- `src/Support/PluginManager.php` - Plugin registration, boot, and dependency resolution
- `src/Facades/Cortex.php` - Fluent interface facade
- `config/cortex.php` - All configuration (~391 lines with extensive comments)

### Plugin Structure

Each plugin follows this pattern:
```
src/Plugins/{PluginName}/
├── {PluginName}Plugin.php        # Implements PluginContract
├── {PluginName}ServiceProvider.php
├── Contracts/                    # Interfaces
├── Data/                         # DTOs using spatie/laravel-data
├── Exceptions/                   # Plugin-specific exceptions
└── ...                           # Implementation classes
```

Plugins implement `PluginContract` with methods: `name()`, `dependencies()`, `register()`, `boot()`, `provides()`, `hooks()`, `extends()`.

### Patterns Used

- **Fluent Builder** - Tools, Agents, Workflows, ChatRequestBuilder
- **Service Locator** - Laravel container for DI
- **Event-Driven** - Events for chat, tool, agent, guardrail, workflow operations
- **Extension Points** - Plugins declare and consume extension points for collaboration

## Testing

- **Framework:** Pest PHP v3 with Orchestra Testbench
- **Database:** In-memory SQLite for tests
- **Mocking:** Mockery + FakeProvider for testing without real API calls

Tests are in `tests/Unit/` and `tests/Feature/`. Base test class at `tests/TestCase.php`.

## Code Standards

- Strict types enabled (`declare(strict_types=1)`)
- PSR-4 autoloading
- Heavy use of interfaces/contracts
- Factory methods for exception creation
- DTOs via Spatie Laravel Data

## Documentation

Plugin-specific documentation exists in `docs/plugins/` for each plugin. The overall plugin system is documented in `docs/plugin-system.md`.
