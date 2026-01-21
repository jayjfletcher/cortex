<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Tool;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use JayI\Cortex\Events\Concerns\DispatchesCortexEvents;
use JayI\Cortex\Events\Tool\ToolRegistered;
use JayI\Cortex\Exceptions\ToolException;
use JayI\Cortex\Plugins\Tool\Attributes\Tool as ToolAttribute;
use JayI\Cortex\Plugins\Tool\Contracts\ToolContract;
use JayI\Cortex\Plugins\Tool\Contracts\ToolRegistryContract;
use ReflectionClass;

class ToolRegistry implements ToolRegistryContract
{
    use DispatchesCortexEvents;
    /**
     * @var Collection<string, ToolContract>
     */
    protected Collection $tools;

    /**
     * @var array<string, class-string<ToolContract>>
     */
    protected array $deferredTools = [];

    /**
     * @param  array<string, string>  $config
     */
    public function __construct(
        protected Container $container,
        protected array $config = [],
    ) {
        $this->tools = new Collection();
    }

    /**
     * {@inheritdoc}
     */
    public function register(ToolContract|string $tool): void
    {
        if (is_string($tool)) {
            // Defer resolution until needed
            $instance = $this->resolveToolClass($tool);
            $this->registerTool($instance);
        } else {
            $this->registerTool($tool);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name): ToolContract
    {
        // Check deferred tools first
        if (isset($this->deferredTools[$name])) {
            $this->register($this->deferredTools[$name]);
            unset($this->deferredTools[$name]);
        }

        if (! $this->tools->has($name)) {
            throw ToolException::notFound($name);
        }

        return $this->tools->get($name);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $name): bool
    {
        return $this->tools->has($name) || isset($this->deferredTools[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function all(): Collection
    {
        // Resolve all deferred tools
        foreach ($this->deferredTools as $name => $class) {
            $this->register($class);
            unset($this->deferredTools[$name]);
        }

        return $this->tools;
    }

    /**
     * {@inheritdoc}
     */
    public function collection(string ...$names): ToolCollection
    {
        $tools = [];
        foreach ($names as $name) {
            $tools[] = $this->get($name);
        }

        return ToolCollection::make($tools);
    }

    /**
     * {@inheritdoc}
     */
    public function discover(): void
    {
        $paths = $this->config['discovery']['paths'] ?? [];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $this->discoverInPath($path);
        }
    }

    /**
     * Execute a tool by name.
     *
     * @param  array<string, mixed>  $input
     */
    public function execute(string $name, array $input, ?ToolContext $context = null): ToolResult
    {
        $tool = $this->get($name);
        $context ??= new ToolContext();

        // Validate input
        $validation = $tool->inputSchema()->validate($input);
        if (! $validation->isValid()) {
            throw ToolException::validationFailed($name, array_map(
                fn ($error) => $error->toArray(),
                $validation->errors
            ));
        }

        try {
            return $tool->execute($input, $context);
        } catch (\Throwable $e) {
            throw ToolException::executionFailed($name, $e->getMessage(), $e);
        }
    }

    /**
     * Get tool names.
     *
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_merge(
            $this->tools->keys()->toArray(),
            array_keys($this->deferredTools)
        );
    }

    /**
     * Register a tool instance.
     */
    protected function registerTool(ToolContract $tool): void
    {
        $name = $tool->name();

        if ($this->tools->has($name)) {
            throw ToolException::alreadyRegistered($name);
        }

        $this->tools->put($name, $tool);

        $this->dispatchCortexEvent(new ToolRegistered(
            tool: $tool,
        ));
    }

    /**
     * Resolve a tool class to an instance.
     *
     * @param  class-string<ToolContract>  $class
     */
    protected function resolveToolClass(string $class): ToolContract
    {
        if (! class_exists($class)) {
            throw ToolException::invalidClass($class, 'Class does not exist');
        }

        $reflection = new ReflectionClass($class);

        if (! $reflection->implementsInterface(ToolContract::class)) {
            throw ToolException::invalidClass($class, 'Class must implement ToolContract');
        }

        return $this->container->make($class);
    }

    /**
     * Discover tools in a path.
     */
    protected function discoverInPath(string $path): void
    {
        $files = File::glob($path.'/*.php');

        foreach ($files as $file) {
            $class = $this->getClassFromFile($file);

            if ($class === null) {
                continue;
            }

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            // Check if class has Tool attribute or implements ToolContract
            $hasToolAttribute = count($reflection->getAttributes(ToolAttribute::class)) > 0;
            $implementsContract = $reflection->implementsInterface(ToolContract::class);

            if ($hasToolAttribute || $implementsContract) {
                try {
                    $this->register($class);
                } catch (\Throwable $e) {
                    // Skip tools that fail to register during discovery
                    continue;
                }
            }
        }
    }

    /**
     * Get the class name from a file.
     */
    protected function getClassFromFile(string $file): ?string
    {
        $contents = file_get_contents($file);
        if ($contents === false) {
            return null;
        }

        $namespace = null;
        $class = null;

        if (preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/class\s+(\w+)/', $contents, $matches)) {
            $class = $matches[1];
        }

        if ($class === null) {
            return null;
        }

        return $namespace !== null ? "{$namespace}\\{$class}" : $class;
    }
}
