<?php

declare(strict_types=1);

namespace JayI\Cortex\Plugins\Schema\Types;

use JayI\Cortex\Plugins\Schema\Schema;
use JayI\Cortex\Plugins\Schema\ValidationResult;

class EnumSchema extends Schema
{
    protected mixed $defaultValue = null;

    /**
     * @param  array<int, string|int|float|bool>  $values
     */
    public function __construct(
        protected array $values,
    ) {}

    /**
     * Set default value.
     */
    public function default(mixed $value): static
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
        $schema = ['enum' => $this->values];

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
        if (! in_array($value, $this->values, true)) {
            $allowedValues = implode(', ', array_map(
                fn ($v) => is_string($v) ? "'{$v}'" : (string) $v,
                $this->values
            ));

            return ValidationResult::error(
                '$',
                "Value must be one of: {$allowedValues}",
                $value
            );
        }

        return ValidationResult::valid();
    }

    /**
     * Cast a value - returns as-is if valid, otherwise returns default or first value.
     */
    public function cast(mixed $value): mixed
    {
        if (in_array($value, $this->values, true)) {
            return $value;
        }

        if ($this->defaultValue !== null) {
            return $this->defaultValue;
        }

        return $this->values[0] ?? null;
    }

    /**
     * Get the allowed values.
     *
     * @return array<int, string|int|float|bool>
     */
    public function getValues(): array
    {
        return $this->values;
    }
}
