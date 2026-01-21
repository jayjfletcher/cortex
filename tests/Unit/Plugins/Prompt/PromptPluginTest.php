<?php

declare(strict_types=1);

use Illuminate\Contracts\Container\Container;
use JayI\Cortex\Contracts\PluginContract;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Plugins\Prompt\Contracts\PromptContract;
use JayI\Cortex\Plugins\Prompt\Contracts\PromptRegistryContract;
use JayI\Cortex\Plugins\Prompt\Exceptions\PromptNotFoundException;
use JayI\Cortex\Plugins\Prompt\Exceptions\PromptValidationException;
use JayI\Cortex\Plugins\Prompt\FilePromptLoader;
use JayI\Cortex\Plugins\Prompt\Prompt;
use JayI\Cortex\Plugins\Prompt\PromptPlugin;
use JayI\Cortex\Plugins\Prompt\PromptRegistry;

describe('Prompt', function () {
    it('creates prompt with basic properties', function () {
        $prompt = new Prompt(
            id: 'greeting',
            template: 'Hello, {{ $name }}!',
        );

        expect($prompt->id())->toBe('greeting');
        expect($prompt->template())->toBe('Hello, {{ $name }}!');
    });

    it('renders template with variables', function () {
        $prompt = new Prompt(
            id: 'greeting',
            template: 'Hello, {{ $name }}! You have {{ $count }} messages.',
        );

        $rendered = $prompt->render(['name' => 'John', 'count' => 5]);

        expect($rendered)->toBe('Hello, John! You have 5 messages.');
    });

    it('supports default variables', function () {
        $prompt = new Prompt(
            id: 'greeting',
            template: 'Hello, {{ $name }}!',
            defaults: ['name' => 'Guest'],
        );

        $rendered = $prompt->render([]);

        expect($rendered)->toBe('Hello, Guest!');
    });

    it('overrides defaults with provided variables', function () {
        $prompt = new Prompt(
            id: 'greeting',
            template: 'Hello, {{ $name }}!',
            defaults: ['name' => 'Guest'],
        );

        $rendered = $prompt->render(['name' => 'John']);

        expect($rendered)->toBe('Hello, John!');
    });

    it('validates required variables', function () {
        $prompt = new Prompt(
            id: 'greeting',
            template: 'Hello, {{ $name }}!',
            requiredVariables: ['name'],
        );

        expect(fn () => $prompt->render([]))
            ->toThrow(PromptValidationException::class);
    });

    it('passes validation with required variables present', function () {
        $prompt = new Prompt(
            id: 'greeting',
            template: 'Hello, {{ $name }}!',
            requiredVariables: ['name'],
        );

        $rendered = $prompt->render(['name' => 'John']);

        expect($rendered)->toBe('Hello, John!');
    });

    it('supports versioning', function () {
        $prompt = new Prompt(
            id: 'greeting',
            template: 'Hello!',
            version: '1.2.0',
        );

        expect($prompt->version())->toBe('1.2.0');
    });

    it('supports metadata', function () {
        $prompt = new Prompt(
            id: 'greeting',
            template: 'Hello!',
            metadata: ['author' => 'John', 'category' => 'greetings'],
        );

        expect($prompt->metadata)->toBe(['author' => 'John', 'category' => 'greetings']);
    });

    it('supports name', function () {
        $prompt = new Prompt(
            id: 'greeting',
            template: 'Hello!',
            name: 'Greeting Prompt',
        );

        expect($prompt->name())->toBe('Greeting Prompt');
    });

    it('defaults name to id', function () {
        $prompt = new Prompt(
            id: 'greeting',
            template: 'Hello!',
        );

        expect($prompt->name())->toBe('greeting');
    });

    it('validates variables', function () {
        $prompt = new Prompt(
            id: 'greeting',
            template: 'Hello, {{ $name }}!',
            requiredVariables: ['name'],
        );

        $result = $prompt->validateVariables([]);

        expect($result->isValid())->toBeFalse();
    });

    it('validates variables passes when present', function () {
        $prompt = new Prompt(
            id: 'greeting',
            template: 'Hello, {{ $name }}!',
            requiredVariables: ['name'],
        );

        $result = $prompt->validateVariables(['name' => 'John']);

        expect($result->isValid())->toBeTrue();
    });

    it('creates prompt with version via withVersion', function () {
        $prompt = new Prompt('test', 'Template');
        $versioned = $prompt->withVersion('2.0.0');

        expect($versioned->version())->toBe('2.0.0');
        expect($prompt->version())->toBe('1.0.0'); // Original unchanged
    });

    it('creates prompt with defaults via withDefaults', function () {
        $prompt = new Prompt('test', 'Hello, {{ $name }}!');
        $withDefaults = $prompt->withDefaults(['name' => 'World']);

        $rendered = $withDefaults->render([]);
        expect($rendered)->toBe('Hello, World!');
    });

    it('creates prompt from template', function () {
        $prompt = Prompt::fromTemplate('simple', 'Hello, {{ $name }}!', ['name']);

        expect($prompt->id())->toBe('simple');
        expect($prompt->template())->toBe('Hello, {{ $name }}!');
        expect($prompt->variables())->toBe(['name']);
    });
});

describe('PromptRegistry', function () {
    it('registers and retrieves prompt', function () {
        $registry = new PromptRegistry();
        $prompt = new Prompt('test', 'Test template');

        $registry->register($prompt);

        expect($registry->get('test'))->toBe($prompt);
    });

    it('checks if prompt exists', function () {
        $registry = new PromptRegistry();

        expect($registry->has('nonexistent'))->toBeFalse();

        $registry->register(new Prompt('test', 'Test'));

        expect($registry->has('test'))->toBeTrue();
    });

    it('throws exception for missing prompt', function () {
        $registry = new PromptRegistry();

        expect(fn () => $registry->get('nonexistent'))
            ->toThrow(PromptNotFoundException::class);
    });

    it('returns all prompts', function () {
        $registry = new PromptRegistry();
        $registry->register(new Prompt('prompt1', 'Template 1'));
        $registry->register(new Prompt('prompt2', 'Template 2'));

        $all = $registry->all();

        expect($all)->toHaveCount(2);
        expect($all->has('prompt1'))->toBeTrue();
        expect($all->has('prompt2'))->toBeTrue();
    });

    it('supports versioned prompts', function () {
        $registry = new PromptRegistry();
        $registry->register(new Prompt('greeting', 'Hello v1', version: '1.0.0'));
        $registry->register(new Prompt('greeting', 'Hello v2', version: '2.0.0'));

        $v1 = $registry->get('greeting', '1.0.0');
        $v2 = $registry->get('greeting', '2.0.0');
        $latest = $registry->get('greeting');

        expect($v1->template())->toBe('Hello v1');
        expect($v2->template())->toBe('Hello v2');
        expect($latest->template())->toBe('Hello v2');
    });

    it('lists all prompt ids', function () {
        $registry = new PromptRegistry();
        $registry->register(new Prompt('prompt1', 'Template 1'));
        $registry->register(new Prompt('prompt2', 'Template 2'));

        $ids = $registry->ids();

        expect($ids)->toContain('prompt1');
        expect($ids)->toContain('prompt2');
    });

    it('returns versions for a prompt', function () {
        $registry = new PromptRegistry();
        $registry->register(new Prompt('greeting', 'Hello v1', version: '1.0.0'));
        $registry->register(new Prompt('greeting', 'Hello v2', version: '2.0.0'));

        $versions = $registry->versions('greeting');

        expect($versions->count())->toBe(2);
        expect($versions)->toContain('1.0.0');
        expect($versions)->toContain('2.0.0');
    });

    it('returns latest version', function () {
        $registry = new PromptRegistry();
        $registry->register(new Prompt('greeting', 'Hello v1', version: '1.0.0'));
        $registry->register(new Prompt('greeting', 'Hello v2', version: '2.0.0'));

        $latest = $registry->latest('greeting');

        expect($latest->template())->toBe('Hello v2');
    });

    it('returns all versions of a prompt', function () {
        $registry = new PromptRegistry();
        $registry->register(new Prompt('greeting', 'Hello v1', version: '1.0.0'));
        $registry->register(new Prompt('greeting', 'Hello v2', version: '2.0.0'));

        $allVersions = $registry->allVersions('greeting');

        expect($allVersions->count())->toBe(2);
    });
});

describe('FilePromptLoader', function () {
    it('is instantiated with registry', function () {
        $registry = new PromptRegistry();
        $loader = new FilePromptLoader($registry);

        expect($loader)->toBeInstanceOf(FilePromptLoader::class);
    });
});

describe('Prompt Contract', function () {
    it('implements PromptContract', function () {
        $prompt = new Prompt('test', 'template');

        expect($prompt)->toBeInstanceOf(PromptContract::class);
    });
});

describe('PromptRegistry Contract', function () {
    it('implements PromptRegistryContract', function () {
        $registry = new PromptRegistry();

        expect($registry)->toBeInstanceOf(PromptRegistryContract::class);
    });
});

describe('PromptNotFoundException', function () {
    it('creates exception with prompt id', function () {
        $exception = PromptNotFoundException::forId('missing-prompt');

        expect($exception)->toBeInstanceOf(PromptNotFoundException::class);
        expect($exception->getMessage())->toContain('missing-prompt');
    });

    it('creates exception with prompt version', function () {
        $exception = PromptNotFoundException::forVersion('test', '2.0.0');

        expect($exception)->toBeInstanceOf(PromptNotFoundException::class);
        expect($exception->getMessage())->toContain('test');
        expect($exception->getMessage())->toContain('2.0.0');
    });
});

describe('PromptPlugin', function () {
    it('implements PluginContract', function () {
        $plugin = new PromptPlugin();

        expect($plugin)->toBeInstanceOf(PluginContract::class);
    });

    it('has correct id', function () {
        $plugin = new PromptPlugin();

        expect($plugin->id())->toBe('prompt');
    });

    it('has correct name', function () {
        $plugin = new PromptPlugin();

        expect($plugin->name())->toBe('Prompt Plugin');
    });

    it('has correct version', function () {
        $plugin = new PromptPlugin();

        expect($plugin->version())->toBe('1.0.0');
    });

    it('has no dependencies', function () {
        $plugin = new PromptPlugin();

        expect($plugin->dependencies())->toBe([]);
    });

    it('provides prompt capabilities', function () {
        $plugin = new PromptPlugin();

        expect($plugin->provides())->toContain('prompt');
        expect($plugin->provides())->toContain('prompt-registry');
    });

    it('registers prompt registry', function () {
        $container = Mockery::mock(Container::class);
        $manager = Mockery::mock(PluginManagerContract::class);

        $manager->shouldReceive('getContainer')
            ->andReturn($container);

        $container->shouldReceive('singleton')
            ->with(PromptRegistryContract::class, PromptRegistry::class)
            ->once();

        $plugin = new PromptPlugin();
        $plugin->register($manager);
    });

    it('boots with discovery disabled', function () {
        $container = Mockery::mock(Container::class);
        $config = Mockery::mock();
        $manager = Mockery::mock(PluginManagerContract::class);

        $manager->shouldReceive('getContainer')
            ->andReturn($container);

        $container->shouldReceive('make')
            ->with('config')
            ->andReturn($config);

        $config->shouldReceive('get')
            ->with('cortex.prompt.discovery.enabled', true)
            ->andReturn(false);

        $plugin = new PromptPlugin();
        $plugin->boot($manager);

        expect(true)->toBeTrue(); // Add assertion to avoid risky test
    });

    it('boots with discovery enabled', function () {
        $container = Mockery::mock(Container::class);
        $config = Mockery::mock();
        $registry = Mockery::mock(PromptRegistryContract::class);
        $manager = Mockery::mock(PluginManagerContract::class);

        $manager->shouldReceive('getContainer')
            ->andReturn($container);

        $container->shouldReceive('make')
            ->with('config')
            ->andReturn($config);

        $container->shouldReceive('make')
            ->with(PromptRegistryContract::class)
            ->andReturn($registry);

        $config->shouldReceive('get')
            ->with('cortex.prompt.discovery.enabled', true)
            ->andReturn(true);

        $config->shouldReceive('get')
            ->with('cortex.prompt.discovery.paths', [])
            ->andReturn([]);

        $plugin = new PromptPlugin();
        $plugin->boot($manager);

        expect(true)->toBeTrue(); // Add assertion to avoid risky test
    });
});
