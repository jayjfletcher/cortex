<?php

declare(strict_types=1);

namespace JayI\Cortex\Support;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use JayI\Cortex\Contracts\ExtensionPointContract;
use JayI\Cortex\Contracts\PluginContract;
use JayI\Cortex\Contracts\PluginManagerContract;
use JayI\Cortex\Exceptions\PluginException;

class PluginManager implements PluginManagerContract
{
    /**
     * @var Collection<string, PluginContract>
     */
    protected Collection $plugins;

    /**
     * @var Collection<string, ExtensionPointContract>
     */
    protected Collection $extensionPoints;

    /**
     * @var array<string, array<array{callback: callable, priority: int}>>
     */
    protected array $hooks = [];

    /**
     * @var array<string, string>
     */
    protected array $replacements = [];

    /**
     * @var array<string, string>
     */
    protected array $featureProviders = [];

    protected bool $booted = false;

    public function __construct(
        protected Container $container,
    ) {
        $this->plugins = new Collection;
        $this->extensionPoints = new Collection;
    }

    /**
     * Register a plugin.
     *
     * @throws PluginException
     */
    public function register(PluginContract $plugin): void
    {
        if ($this->booted) {
            throw PluginException::alreadyBooted($plugin->id());
        }

        if ($this->has($plugin->id())) {
            throw PluginException::alreadyRegistered($plugin->id());
        }

        $this->plugins->put($plugin->id(), $plugin);

        // Track provided features
        foreach ($plugin->provides() as $feature) {
            $this->featureProviders[$feature] = $plugin->id();
        }

        // Call plugin's register method
        $plugin->register($this);
    }

    /**
     * Boot all registered plugins in dependency order.
     *
     * @throws PluginException
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->validateDependencies();

        $sortedPlugins = $this->sortByDependencies();

        foreach ($sortedPlugins as $plugin) {
            $plugin->boot($this);
        }

        $this->booted = true;
    }

    /**
     * Get a registered plugin by ID.
     */
    public function get(string $id): ?PluginContract
    {
        return $this->plugins->get($id);
    }

    /**
     * Check if a plugin is registered.
     */
    public function has(string $id): bool
    {
        return $this->plugins->has($id);
    }

    /**
     * Get all registered plugins.
     *
     * @return Collection<string, PluginContract>
     */
    public function all(): Collection
    {
        return $this->plugins;
    }

    /**
     * Check if a feature is provided by any plugin.
     */
    public function hasFeature(string $feature): bool
    {
        return isset($this->featureProviders[$feature]);
    }

    /**
     * Get the plugin providing a feature.
     */
    public function getFeatureProvider(string $feature): ?PluginContract
    {
        if (! isset($this->featureProviders[$feature])) {
            return null;
        }

        return $this->get($this->featureProviders[$feature]);
    }

    /**
     * Register an extension point.
     */
    public function registerExtensionPoint(string $name, ExtensionPointContract $point): void
    {
        $this->extensionPoints->put($name, $point);
    }

    /**
     * Get an extension point by name.
     */
    public function getExtensionPoint(string $name): ?ExtensionPointContract
    {
        return $this->extensionPoints->get($name);
    }

    /**
     * Extend an extension point.
     *
     * @throws PluginException
     */
    public function extend(string $extensionPoint, mixed $extension): void
    {
        $point = $this->getExtensionPoint($extensionPoint);

        if ($point === null) {
            throw PluginException::extensionPointNotFound($extensionPoint);
        }

        $point->register($extension);
    }

    /**
     * Register a hook (filter/modify data).
     */
    public function addHook(string $name, callable $callback, int $priority = 10): void
    {
        if (! isset($this->hooks[$name])) {
            $this->hooks[$name] = [];
        }

        $this->hooks[$name][] = [
            'callback' => $callback,
            'priority' => $priority,
        ];

        // Sort hooks by priority (higher priority runs first)
        usort($this->hooks[$name], fn ($a, $b) => $b['priority'] <=> $a['priority']);
    }

    /**
     * Apply hooks to filter data.
     */
    public function applyHooks(string $name, mixed $value, mixed ...$args): mixed
    {
        if (! isset($this->hooks[$name])) {
            return $value;
        }

        foreach ($this->hooks[$name] as $hook) {
            $value = call_user_func($hook['callback'], $value, ...$args);
        }

        return $value;
    }

    /**
     * Replace a bound implementation.
     */
    public function replace(string $abstract, string $concrete): void
    {
        $this->replacements[$abstract] = $concrete;
        $this->container->bind($abstract, $concrete);
    }

    /**
     * Check if the manager has booted.
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Get all replacements.
     *
     * @return array<string, string>
     */
    public function getReplacements(): array
    {
        return $this->replacements;
    }

    /**
     * Validate that all plugin dependencies are satisfied.
     *
     * @throws PluginException
     */
    protected function validateDependencies(): void
    {
        foreach ($this->plugins as $plugin) {
            foreach ($plugin->dependencies() as $dependency) {
                if (! $this->has($dependency)) {
                    throw PluginException::dependencyNotFound($plugin->id(), $dependency);
                }
            }
        }
    }

    /**
     * Sort plugins by dependencies using topological sort.
     *
     * @return Collection<int, PluginContract>
     *
     * @throws PluginException
     */
    protected function sortByDependencies(): Collection
    {
        $sorted = [];
        $visited = [];
        $visiting = [];

        foreach ($this->plugins as $plugin) {
            $this->visit($plugin, $sorted, $visited, $visiting);
        }

        return new Collection($sorted);
    }

    /**
     * Visit a plugin for topological sort.
     *
     * @param  array<int, PluginContract>  $sorted
     * @param  array<string, bool>  $visited
     * @param  array<string, bool>  $visiting
     *
     * @throws PluginException
     */
    protected function visit(PluginContract $plugin, array &$sorted, array &$visited, array &$visiting): void
    {
        $id = $plugin->id();

        if (isset($visiting[$id])) {
            throw PluginException::circularDependency($id);
        }

        if (isset($visited[$id])) {
            return;
        }

        $visiting[$id] = true;

        foreach ($plugin->dependencies() as $dependencyId) {
            $dependency = $this->get($dependencyId);
            if ($dependency !== null) {
                $this->visit($dependency, $sorted, $visited, $visiting);
            }
        }

        unset($visiting[$id]);
        $visited[$id] = true;
        $sorted[] = $plugin;
    }
}
