<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Schema\Types;

use Closure;
use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Schema\ValidationError;
use JayI\Cortex\Plugins\Schema\ValidationResult;

class ObjectSchema extends Schema
{
    /**
     * @var array<string, Schema>
     */
    protected array $properties = [];

    /**
     * @var array<int, string>
     */
    protected array $requiredProperties = [];

    protected bool|Schema $additionalProperties = true;

    /**
     * Add a property to this schema.
     */
    public function property(string $name, Schema $schema): static
    {
        $this->properties[$name] = $schema;

        return $this;
    }

    /**
     * Add multiple properties at once.
     *
     * @param  array<string, Schema>  $properties
     */
    public function properties(array $properties): static
    {
        foreach ($properties as $name => $schema) {
            $this->property($name, $schema);
        }

        return $this;
    }

    /**
     * Mark properties as required.
     */
    public function required(string ...$names): static
    {
        $this->requiredProperties = array_unique(
            array_merge($this->requiredProperties, $names)
        );

        return $this;
    }

    /**
     * Configure additional properties.
     */
    public function additionalProperties(bool|Schema $value): static
    {
        $this->additionalProperties = $value;

        return $this;
    }

    /**
     * Add a nested object property.
     */
    public function nested(string $name, Closure $callback): static
    {
        $nestedSchema = new static();
        $callback($nestedSchema);

        return $this->property($name, $nestedSchema);
    }

    /**
     * Convert this schema to a JSON Schema array.
     *
     * @return array<string, mixed>
     */
    public function toJsonSchema(): array
    {
        $schema = ['type' => 'object'];

        if (count($this->properties) > 0) {
            $schema['properties'] = [];
            foreach ($this->properties as $name => $propertySchema) {
                $schema['properties'][$name] = $propertySchema->toJsonSchema();
            }
        }

        if (count($this->requiredProperties) > 0) {
            $schema['required'] = $this->requiredProperties;
        }

        if ($this->additionalProperties === false) {
            $schema['additionalProperties'] = false;
        } elseif ($this->additionalProperties instanceof Schema) {
            $schema['additionalProperties'] = $this->additionalProperties->toJsonSchema();
        }

        return $this->addDescriptionToSchema($schema);
    }

    /**
     * Validate a value against this schema.
     */
    public function validate(mixed $value): ValidationResult
    {
        if (! is_array($value) && ! is_object($value)) {
            return ValidationResult::error('$', 'Value must be an object', $value);
        }

        $value = (array) $value;
        $errors = [];

        // Check required properties
        foreach ($this->requiredProperties as $required) {
            if (! array_key_exists($required, $value)) {
                $errors[] = new ValidationError(
                    "\$.{$required}",
                    "Property '{$required}' is required",
                    null
                );
            }
        }

        // Validate each defined property
        foreach ($this->properties as $name => $propertySchema) {
            if (array_key_exists($name, $value)) {
                $result = $propertySchema->validate($value[$name]);
                if (! $result->isValid()) {
                    foreach ($result->errors as $error) {
                        $path = $error->path === '$' ? "\$.{$name}" : "\$.{$name}".substr($error->path, 1);
                        $errors[] = new ValidationError($path, $error->message, $error->value);
                    }
                }
            }
        }

        // Check additional properties
        if ($this->additionalProperties === false) {
            $definedProperties = array_keys($this->properties);
            foreach (array_keys($value) as $key) {
                if (! in_array($key, $definedProperties, true)) {
                    $errors[] = new ValidationError(
                        "\$.{$key}",
                        "Additional property '{$key}' is not allowed",
                        $value[$key]
                    );
                }
            }
        } elseif ($this->additionalProperties instanceof Schema) {
            $definedProperties = array_keys($this->properties);
            foreach ($value as $key => $val) {
                if (! in_array($key, $definedProperties, true)) {
                    $result = $this->additionalProperties->validate($val);
                    if (! $result->isValid()) {
                        foreach ($result->errors as $error) {
                            $path = $error->path === '$' ? "\$.{$key}" : "\$.{$key}".substr($error->path, 1);
                            $errors[] = new ValidationError($path, $error->message, $error->value);
                        }
                    }
                }
            }
        }

        if (count($errors) > 0) {
            return ValidationResult::invalid($errors);
        }

        return ValidationResult::valid();
    }

    /**
     * Cast a value to an object/array with cast properties.
     *
     * @return array<string, mixed>
     */
    public function cast(mixed $value): array
    {
        if (! is_array($value) && ! is_object($value)) {
            return [];
        }

        $value = (array) $value;
        $result = [];

        foreach ($this->properties as $name => $propertySchema) {
            if (array_key_exists($name, $value)) {
                $result[$name] = $propertySchema->cast($value[$name]);
            }
        }

        // Handle additional properties
        if ($this->additionalProperties !== false) {
            $definedProperties = array_keys($this->properties);
            foreach ($value as $key => $val) {
                if (! in_array($key, $definedProperties, true)) {
                    if ($this->additionalProperties instanceof Schema) {
                        $result[$key] = $this->additionalProperties->cast($val);
                    } else {
                        $result[$key] = $val;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Get defined properties.
     *
     * @return array<string, Schema>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Get required properties.
     *
     * @return array<int, string>
     */
    public function getRequired(): array
    {
        return $this->requiredProperties;
    }
}
