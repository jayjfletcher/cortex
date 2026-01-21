<?php

declare(strict_types=1);

use Illuminate\Contracts\Container\Container;
use JayI\Cortex\Contracts\PluginContract;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Exceptions\PluginException;
use JayI\Cortex\Support\ExtensionPoint;
use JayI\Cortex\Support\PluginManager;

beforeEach(function () {
    $this->container = Mockery::mock(Container::class)->shouldIgnoreMissing();
    $this->manager = new PluginManager($this->container);
});

describe('PluginManager', function () {
    it('registers a plugin', function () {
        $plugin = createTestPlugin('test-plugin');

        $this->manager->register($plugin);

        expect($this->manager->has('test-plugin'))->toBeTrue();
        expect($this->manager->get('test-plugin'))->toBe($plugin);
    });

    it('throws exception when registering duplicate plugin', function () {
        $plugin = createTestPlugin('test-plugin');

        $this->manager->register($plugin);

        expect(fn () => $this->manager->register($plugin))
            ->toThrow(PluginException::class);
    });

    it('throws exception when registering after boot', function () {
        $plugin = createTestPlugin('test-plugin');

        $this->manager->register($plugin);
        $this->manager->boot();

        $plugin2 = createTestPlugin('test-plugin-2');

        expect(fn () => $this->manager->register($plugin2))
            ->toThrow(PluginException::class);
    });

    it('boots plugins in dependency order', function () {
        $bootOrder = [];

        $pluginA = createTestPlugin('plugin-a', [], [], function () use (&$bootOrder) {
            $bootOrder[] = 'a';
        });

        $pluginB = createTestPlugin('plugin-b', ['plugin-a'], [], function () use (&$bootOrder) {
            $bootOrder[] = 'b';
        });

        $pluginC = createTestPlugin('plugin-c', ['plugin-b'], [], function () use (&$bootOrder) {
            $bootOrder[] = 'c';
        });

        // Register in reverse order to test sorting
        $this->manager->register($pluginC);
        $this->manager->register($pluginA);
        $this->manager->register($pluginB);

        $this->manager->boot();

        expect($bootOrder)->toBe(['a', 'b', 'c']);
    });

    it('throws exception for missing dependency', function () {
        $plugin = createTestPlugin('plugin-a', ['missing-plugin']);

        $this->manager->register($plugin);

        expect(fn () => $this->manager->boot())
            ->toThrow(PluginException::class);
    });

    it('throws exception for circular dependency', function () {
        $pluginA = createTestPlugin('plugin-a', ['plugin-b']);
        $pluginB = createTestPlugin('plugin-b', ['plugin-a']);

        $this->manager->register($pluginA);
        $this->manager->register($pluginB);

        expect(fn () => $this->manager->boot())
            ->toThrow(PluginException::class);
    });

    it('tracks provided features', function () {
        $plugin = createTestPlugin('test-plugin', [], ['feature-1', 'feature-2']);

        $this->manager->register($plugin);

        expect($this->manager->hasFeature('feature-1'))->toBeTrue();
        expect($this->manager->hasFeature('feature-2'))->toBeTrue();
        expect($this->manager->hasFeature('feature-3'))->toBeFalse();
        expect($this->manager->getFeatureProvider('feature-1'))->toBe($plugin);
    });

    it('returns all registered plugins', function () {
        $pluginA = createTestPlugin('plugin-a');
        $pluginB = createTestPlugin('plugin-b');

        $this->manager->register($pluginA);
        $this->manager->register($pluginB);

        $all = $this->manager->all();

        expect($all)->toHaveCount(2);
        expect($all->has('plugin-a'))->toBeTrue();
        expect($all->has('plugin-b'))->toBeTrue();
    });
});

describe('Extension Points', function () {
    it('registers extension points', function () {
        $point = ExtensionPoint::make('test-point', stdClass::class);

        $this->manager->registerExtensionPoint('test-point', $point);

        expect($this->manager->getExtensionPoint('test-point'))->toBe($point);
    });

    it('extends extension points', function () {
        $point = ExtensionPoint::make('test-point', stdClass::class);
        $this->manager->registerExtensionPoint('test-point', $point);

        $extension = new stdClass;
        $extension->name = 'test';

        $this->manager->extend('test-point', $extension);

        expect($point->all())->toHaveCount(1);
        expect($point->all()->first())->toBe($extension);
    });

    it('throws exception for unknown extension point', function () {
        expect(fn () => $this->manager->extend('unknown', new stdClass))
            ->toThrow(PluginException::class);
    });
});

describe('Hooks', function () {
    it('registers and applies hooks', function () {
        $this->manager->addHook('test.hook', fn ($value) => $value * 2);

        $result = $this->manager->applyHooks('test.hook', 5);

        expect($result)->toBe(10);
    });

    it('applies hooks in priority order', function () {
        $this->manager->addHook('test.hook', fn ($value) => $value.'-low', priority: 1);
        $this->manager->addHook('test.hook', fn ($value) => $value.'-high', priority: 10);

        $result = $this->manager->applyHooks('test.hook', 'start');

        // Higher priority runs first
        expect($result)->toBe('start-high-low');
    });

    it('passes additional arguments to hooks', function () {
        $this->manager->addHook('test.hook', fn ($value, $multiplier) => $value * $multiplier);

        $result = $this->manager->applyHooks('test.hook', 5, 3);

        expect($result)->toBe(15);
    });

    it('returns original value when no hooks registered', function () {
        $result = $this->manager->applyHooks('unknown.hook', 'original');

        expect($result)->toBe('original');
    });
});

describe('Replacements', function () {
    it('replaces bindings', function () {
        $this->container->expects('bind')
            ->once()
            ->with('SomeInterface', 'NewImplementation');

        $this->manager->replace('SomeInterface', 'NewImplementation');

        expect($this->manager->getReplacements())->toHaveKey('SomeInterface');
        expect($this->manager->getReplacements()['SomeInterface'])->toBe('NewImplementation');
    });
});

// Helper function to create test plugins
function createTestPlugin(
    string $id,
    array $dependencies = [],
    array $provides = [],
    ?Closure $bootCallback = null
): PluginContract {
    return new class($id, $dependencies, $provides, $bootCallback) implements PluginContract
    {
        public function __construct(
            private string $pluginId,
            private array $deps,
            private array $prov,
            private ?Closure $bootCb,
        ) {}

        public function id(): string
        {
            return $this->pluginId;
        }

        public function name(): string
        {
            return 'Test Plugin: '.$this->pluginId;
        }

        public function version(): string
        {
            return '1.0.0';
        }

        public function dependencies(): array
        {
            return $this->deps;
        }

        public function provides(): array
        {
            return $this->prov;
        }

        public function register(PluginManagerContract $manager): void {}

        public function boot(PluginManagerContract $manager): void
        {
            if ($this->bootCb !== null) {
                ($this->bootCb)();
            }
        }
    };
}
