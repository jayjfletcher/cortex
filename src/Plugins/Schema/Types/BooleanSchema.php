<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Schema\Types;

use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Schema\ValidationResult;

class BooleanSchema extends Schema
{
    protected ?bool $defaultValue = null;

    /**
     * Set default value.
     */
    public function default(bool $value): static
    {
        $this->defaultValue = $value;

        return $this;
    }

    /**
     * Convert this schema to a JSON Schema array.
     *
     * @return array<string, mixed>
     */
    public function toJsonSchema(): array
    {
        $schema = ['type' => 'boolean'];

        if ($this->defaultValue !== null) {
            $schema['default'] = $this->defaultValue;
        }

        return $this->addDescriptionToSchema($schema);
    }

    /**
     * Validate a value against this schema.
     */
    public function validate(mixed $value): ValidationResult
    {
        if (! is_bool($value)) {
            return ValidationResult::error('$', 'Value must be a boolean', $value);
        }

        return ValidationResult::valid();
    }

    /**
     * Cast a value to a boolean.
     */
    public function cast(mixed $value): bool
    {
        if ($value === null && $this->defaultValue !== null) {
            return $this->defaultValue;
        }

        return (bool) $value;
    }
}
