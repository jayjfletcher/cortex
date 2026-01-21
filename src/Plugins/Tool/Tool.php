<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Tool;

use Closure;
use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Tool\Contracts\ToolContract;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;

class Tool implements ToolContract
{
    protected string $toolName;

    protected string $toolDescription = '';

    protected Schema $toolInputSchema;

    protected ?Schema $toolOutputSchema = null;

    protected ?Closure $handler = null;

    protected ?int $toolTimeout = null;

    /**
     * Create a new tool builder.
     */
    public static function make(string $name): static
    {
        $tool = new static;
        $tool->toolName = $name;
        $tool->toolInputSchema = Schema::object();

        return $tool;
    }

    /**
     * Create a tool from an invokable class.
     *
     * @param  class-string  $class
     */
    public static function fromInvokable(string $class): static
    {
        $tool = new static;
        $reflection = new ReflectionClass($class);

        if (! $reflection->hasMethod('__invoke')) {
            throw new RuntimeException("Class {$class} must have an __invoke method");
        }

        $tool->toolName = self::classToToolName($class);
        $tool->toolInputSchema = self::inferSchemaFromMethod($reflection->getMethod('__invoke'));
        $tool->handler = function (array $input, ToolContext $context) use ($class) {
            $instance = app($class);
            $result = $instance(...array_values($input));

            return $result instanceof ToolResult ? $result : ToolResult::success($result);
        };

        return $tool;
    }

    /**
     * Set the tool name.
     */
    public function withName(string $name): static
    {
        $this->toolName = $name;

        return $this;
    }

    /**
     * Set the tool description.
     */
    public function withDescription(string $description): static
    {
        $this->toolDescription = $description;

        return $this;
    }

    /**
     * Set the input schema.
     */
    public function withInput(Schema $schema): static
    {
        $this->toolInputSchema = $schema;

        return $this;
    }

    /**
     * Set the output schema.
     */
    public function withOutput(Schema $schema): static
    {
        $this->toolOutputSchema = $schema;

        return $this;
    }

    /**
     * Set the handler function.
     */
    public function withHandler(Closure $handler): static
    {
        $this->handler = $handler;

        return $this;
    }

    /**
     * Set the timeout in seconds.
     */
    public function withTimeout(?int $seconds): static
    {
        $this->toolTimeout = $seconds;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function name(): string
    {
        return $this->toolName;
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return $this->toolDescription;
    }

    /**
     * {@inheritdoc}
     */
    public function inputSchema(): Schema
    {
        return $this->toolInputSchema;
    }

    /**
     * {@inheritdoc}
     */
    public function outputSchema(): ?Schema
    {
        return $this->toolOutputSchema;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(array $input, ToolContext $context): ToolResult
    {
        if ($this->handler === null) {
            throw new RuntimeException("No handler defined for tool '{$this->toolName}'");
        }

        $result = ($this->handler)($input, $context);

        return $result instanceof ToolResult ? $result : ToolResult::success($result);
    }

    /**
     * {@inheritdoc}
     */
    public function timeout(): ?int
    {
        return $this->toolTimeout;
    }

    /**
     * {@inheritdoc}
     */
    public function toDefinition(): array
    {
        return [
            'name' => $this->toolName,
            'description' => $this->toolDescription,
            'input_schema' => $this->toolInputSchema->toJsonSchema(),
        ];
    }

    /**
     * Convert a class name to a tool name.
     */
    protected static function classToToolName(string $class): string
    {
        $shortName = class_basename($class);

        // Remove common suffixes
        $shortName = preg_replace('/Tool$/', '', $shortName) ?? $shortName;

        // Convert to snake_case
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName) ?? $shortName);
    }

    /**
     * Infer schema from a method's parameters.
     */
    protected static function inferSchemaFromMethod(ReflectionMethod $method): Schema
    {
        $schema = Schema::object();
        $required = [];

        foreach ($method->getParameters() as $param) {
            $type = $param->getType();
            $paramSchema = self::typeToSchema($type);

            if ($paramSchema !== null) {
                $schema->property($param->getName(), $paramSchema);

                if (! $param->isOptional() && ! $param->allowsNull()) {
                    $required[] = $param->getName();
                }
            }
        }

        if (count($required) > 0) {
            $schema->required(...$required);
        }

        return $schema;
    }

    /**
     * Convert a reflection type to a schema.
     */
    protected static function typeToSchema(?\ReflectionType $type): ?Schema
    {
        if ($type === null) {
            return Schema::string();
        }

        if (! $type instanceof ReflectionNamedType) {
            return Schema::string();
        }

        return match ($type->getName()) {
            'string' => Schema::string(),
            'int', 'integer' => Schema::integer(),
            'float', 'double' => Schema::number(),
            'bool', 'boolean' => Schema::boolean(),
            'array' => Schema::array(Schema::string()),
            default => Schema::string(),
        };
    }
}
